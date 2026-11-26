# 個人情報の検出（llm-inspect-server）

相談文に含まれる個人情報と思われる記述の検出は、このアプリケーションの中ではなく、同じサーバーで動かす [llm-inspect](https://github.com/TakahiroNago/llm-inspect) の HTTP サービス（`llm-inspect-server`）で行います。検出のルールは llm-inspect の 1 か所だけにあり、このリポジトリには置きません。

**本番へ反映する順序が決まっています。** 先に `llm-inspect-server` を本番サーバーで動かしてから、このアプリケーションを配備してください。逆の順で配備すると、相談を受け付けられなくなります（「接続できないとき」を参照）。

## 構成

```
ブラウザ ─▶ Nginx ─▶ PHP-FPM（ZEN）
                        │  POST http://127.0.0.1:8787/scan   {"text": "相談文"}
                        ▼
                 llm-inspect-server（127.0.0.1 のみで待ち受け）
                        既定ルール + personal-ja パック
```

| 役割 | 場所 |
| --- | --- |
| 何を個人情報とみなすか（正規表現のルール） | llm-inspect の `rules/default.toml` と `rules/personal-ja.toml` |
| サーバーを呼ぶ処理 | `app/Services/LlmInspectScanner.php` |
| ルールの ID と、このアプリでの種類（氏名・住所など）の対応、伏せ字の表示 | `app/Services/PersonalInfoDetector.php` |

- 通信は同じサーバーの中（ループバック）だけです。相談文は外部へ送信されません。
- `llm-inspect-server` はリクエストの本文をログに書きません。アプリ側も、失敗時の例外に相談文やサーバーの応答の本文を入れません。
- 検出はパターン照合で、すべての個人情報を見つけられるわけではありません。敬称や役職の付かない氏名は検出できません。

## 検出を使っている箇所

| 箇所 | 用途 |
| --- | --- |
| 相談の送信（`ZenController::respond`） | 送信前の注意喚起、伏せ字にした相談文の提案、`contains_personal_info` の記録 |
| 「今日のひとつ」（`ConsultationHighlights`） | 個人情報を含む場合は表示しない |
| 共有 URL（`PublicConsultationShareService`） | 個人情報を含む相談文は共有ページに表示しない |

## 接続できないとき

確認できなかった文章は、個人情報を含むものとして扱います。確認していない文章を AI へ送ったり、公開したりはしません。

| 操作 | 動作 |
| --- | --- |
| 相談の送信 | 受け付けない（503）。入力した文章は画面に残り、クレジットは消費しない。OpenAI は呼ばない |
| ゲストの相談の共有 URL の発行 | 発行しない（503）。画面には「公開用URLを作成できませんでした」と表示される |
| 発行済みの共有ページの表示（保存済みの相談） | 表示する。相談文は非表示にする |
| 「今日のひとつ」 | 表示しない |
| 保存済みの相談の共有 URL の発行 | 発行する（発行時には文章を処理しないため。表示のときに上の規則が働く） |

失敗は `PersonalInfoDetectionUnavailable` としてログ（`laravel.log`）に残ります。メッセージに含まれるのは、接続できなかったこと、または応答のステータスだけです。

## 本番サーバーへの導入

実行場所：本番の VPS（root または sudo）。**IROCA（tarot-app）と同じサーバーなので、Nginx・PHP-FPM・MySQL の設定は変更しません。** 追加するのは、ループバックだけで待ち受ける systemd のサービス 1 つです。メモリの使用量は十数 MB 程度です（開発機での実測で約 13 MB。ルールをコンパイルして保持するだけで、リクエストの内容は保存しません）。

### 1. バイナリを用意する

```bash
# Rust（rustup）が入っている環境でビルドする。本番の VPS でビルドしてもよいが、
# ビルド中は CPU とメモリを使うので、別の Linux（x86_64）でビルドして転送するほうが安全
cargo install --locked \
  --git https://github.com/TakahiroNago/llm-inspect \
  --tag v0.2.0 \
  llm-inspect-server

sudo install -o root -g root -m 0755 ~/.cargo/bin/llm-inspect-server /usr/local/bin/llm-inspect-server
llm-inspect-server --version
```

`--tag` の版は、`.github/workflows/ci.yml` の `LLM_INSPECT_VERSION` と同じにします。CI はその版のサーバーに対して `tests/Integration` を実行します。

### 2. systemd のサービスを作る

`/etc/systemd/system/llm-inspect.service`

```ini
[Unit]
Description=llm-inspect-server (personal information detection for ZEN)
After=network.target

[Service]
ExecStart=/usr/local/bin/llm-inspect-server
Environment=LLM_INSPECT_BIND=127.0.0.1:8787
Environment=LLM_INSPECT_PACKS=personal-ja
Restart=always
RestartSec=2

# 専用の一時ユーザーで動かし、ファイルシステムとネットワークを絞る
DynamicUser=yes
NoNewPrivileges=yes
ProtectSystem=strict
ProtectHome=yes
PrivateTmp=yes
PrivateDevices=yes
RestrictAddressFamilies=AF_INET AF_INET6
IPAddressDeny=any
IPAddressAllow=localhost
MemoryMax=256M

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now llm-inspect
systemctl status llm-inspect --no-pager
```

ポート 8787 がほかで使われている場合は、`LLM_INSPECT_BIND` と、アプリの `LLM_INSPECT_URL` を同じ値に変えます。

### 3. 動作を確認する

```bash
curl -s http://127.0.0.1:8787/healthz
# {"status":"ok","version":"0.2.0"}

curl -s -H 'Content-Type: application/json' \
  -d '{"text":"田中さんに 090-1234-5678 で連絡"}' http://127.0.0.1:8787/scan
# findings に pii.ja.person_name と pii.ja.phone が含まれること

# 外部から届かないこと（別のマシンから実行して、接続できなければよい）
curl -m 5 http://<サーバーの IP>:8787/healthz
```

### 4. アプリケーションの設定

`.env`（既定値のままなら追加は不要です）

| キー | 既定値 | 内容 |
| --- | --- | --- |
| `LLM_INSPECT_URL` | `http://127.0.0.1:8787` | サーバーの URL |
| `LLM_INSPECT_TOKEN` | 空 | サーバーに `LLM_INSPECT_TOKEN` を設定した場合だけ、同じ値を設定する。ループバックだけで待ち受ける構成では不要 |
| `LLM_INSPECT_CONNECT_TIMEOUT` | `1` | 接続の待ち時間（秒） |
| `LLM_INSPECT_TIMEOUT` | `3` | 応答の待ち時間（秒） |

### 5. アプリケーションを配備する

1〜3 が済んでから、通常どおり配備します。配備後に、本番で相談を 1 件送信できること、個人情報を含む文章で注意が表示されることを確認します。

## 運用

| 作業 | 方法 |
| --- | --- |
| 状態の確認 | `systemctl status llm-inspect`、`curl -s http://127.0.0.1:8787/healthz` |
| ログ | `journalctl -u llm-inspect`（メソッド・パス・ステータス・所要時間のみ。本文は出ない） |
| ルールの確認 | `curl -s http://127.0.0.1:8787/rules` |
| ルールの変更 | llm-inspect を変更して新しい版を出し、バイナリを入れ替えて `systemctl restart llm-inspect`。新しい種類の個人情報を増やす場合だけ、`PersonalInfoDetector::RULE_TYPES` に対応を追加する |
| 版を上げる | `ci.yml` の `LLM_INSPECT_VERSION` を上げて CI を通し、本番のバイナリを同じ版に入れ替える |
| 監視 | サービスが止まると相談を受け付けられなくなる。`/healthz` を外形監視か cron で確認し、`laravel.log` の `PersonalInfoDetectionUnavailable` に気付けるようにする |

サーバーの再起動中（数秒）に届いた相談は 503 になります。アプリは接続の失敗を 1 回だけ再試行します。

## 開発とテスト

- 通常のテスト（`php artisan test`）はサーバーを必要としません。`Tests\TestCase` がスキャナーを `Tests\Support\FakePersonalInfoScanner` に差し替えます。検出が必要なテストは、`personalInfoScanner()->flag('boss@example.com', 'pii.email')` のように、サーバーが報告する内容を宣言します。
- 実際のサーバーとの組み合わせは `tests/Integration/LlmInspectContractTest.php` で確認します。`LLM_INSPECT_TEST_URL` を設定したときだけ実行されます。

  ```bash
  # llm-inspect のリポジトリで
  cargo run --release -p llm-inspect-server -- --bind 127.0.0.1:8787 --pack personal-ja

  # このリポジトリで（PowerShell の場合は $env:LLM_INSPECT_TEST_URL = 'http://127.0.0.1:8787'）
  LLM_INSPECT_TEST_URL=http://127.0.0.1:8787 vendor/bin/pest tests/Integration
  ```

- ローカルで相談の画面を動かすには、同じようにサーバーを起動しておきます。起動していないと、相談の送信は 503 になります。

## 以前の実装との違い

以前は `PersonalInfoDetector` が PHP の正規表現を持っていました。置き換えにあたって、次の点が変わっています。

- 伏せ字にした文章は、検出した箇所だけを置き換えます。以前は文章全体の全角英数字を半角に直していました。
- `+81` 以外の国番号の電話番号、区切りのない電話番号も電話番号として検出します。
- `090-…,080-…` のように 1 文字だけを挟んで並ぶ電話番号は、両方とも伏せ字になります。
