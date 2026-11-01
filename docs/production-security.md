# 本番環境のセキュリティ設定

相談内容（ユーザーの悩みとAIの回答）がログ・セッション・キャッシュ・外部サービスに意図せず残らないようにするための、本番環境の推奨設定と確認手順です。

## アプリ側で常に有効な対策

以下はコードで強制されるため、サーバー設定に依存しません。

| 対策 | 実装箇所 |
| --- | --- |
| 例外トレースに関数引数を記録しない（`zend.exception_ignore_args=1` を実行時に設定） | `bootstrap/app.php` |
| 相談内容を受け取る引数に `#[\SensitiveParameter]` を付与（多層防御） | `ZenController`, `PersonalInfoDetector` など |
| SQL例外のメッセージ・`getBindings()` からバインド値を除去 | `bootstrap/app.php`, `config/database.php` |
| バリデーション失敗時に `concern` を入力フラッシュしない | `bootstrap/app.php` (`dontFlash`) |
| 個人情報検出時はリダイレクトせず画面を直接返す（セッションに相談文を保存しない） | `ZenController::respond` |
| 全ログにサーバー生成のリクエストID（`request_id`）を付与し、`X-Request-Id` ヘッダーで返す | `AssignRequestId` |
| 共有URLはユーザーが「シェア」操作をしたときだけ発行 | `ConsultationHistoryController::share` |
| `img-src 'self' data:` のCSPで外部画像の読み込みを禁止 | `RestrictImageSources` |
| AI回答のMarkdownで生HTML・危険なURL・画像を出力しない | `ConsultationResponseFormatter` |

## 推奨する本番 `.env`

雛形は `.env.production.example` です。秘密情報は記載していません。

| キー | 推奨値 | 理由 |
| --- | --- | --- |
| `APP_ENV` | `production` | 本番向けの挙動にする |
| `APP_DEBUG` | `false` | エラー画面にリクエスト内容・環境変数を表示しない |
| `APP_KEY` | **既存の値を維持** | 変更するとセッション・暗号化済み共有トークンが復号できなくなる |
| `LOG_STACK` | `daily` | ログを日別に分割する |
| `LOG_DAILY_DAYS` | `14` | 古いログを自動削除する |
| `LOG_LEVEL` | `warning` | debug/info レベルの詳細ログを出さない |
| `SESSION_ENCRYPT` | `true` | セッション（ゲストの相談内容を含む）をDB上で暗号化する |
| `SESSION_SECURE_COOKIE` | `true` | HTTPSでのみセッションCookieを送る（HTTPS運用が前提） |

## php.ini（PHP-FPM と CLI）

```ini
zend.exception_ignore_args = On
```

アプリ側で実行時に同じ設定をしているため必須ではありません。ただし、Laravelの起動前に発生したエラーにも効くよう、本番の php.ini でも `On` にすることを推奨します。PHP公式の本番用 php.ini（`php.ini-production`）の既定値も `On` です。変更後は PHP-FPM の reload が必要です。

## デプロイ手順と再起動の要否

1. PRをマージする。
2. **デプロイ前に**本番の `.env` を上記の推奨値に更新する（`APP_KEY` は変更しない）。
3. 通常どおりデプロイする。`deploy-zen` の `migrate`（共有トークン用のカラムを追加）と `optimize`（設定キャッシュの再生成）で `.env` の変更が反映される。
   - コードだけを反映してマイグレーションを実行しないと、「シェア」が500エラーになる（`Unknown column 'share_token'`）。必ずコードとマイグレーションを同時に反映する。
4. PHP-FPM の reload（例: `sudo systemctl reload php8.4-fpm`）が必要になるのは次の場合。
   - `opcache.validate_timestamps=0` の場合（コードの変更を反映するため）
   - php.ini を変更した場合

### 日別ログ（`LOG_STACK=daily`）とファイル権限

`deploy-zen` は root で実行され、その中で `migrate` と `optimize` も root で動く。日別ログでは日付ごとに新しいファイル（`storage/logs/laravel-YYYY-MM-DD.log`）が作られるため、その日の最初のログを root のプロセスが書くと、ファイルの所有者が root になる。その結果、PHP-FPM（www-data）がその日のログに書き込めず、その日のエラーが記録されなくなる。

対策として、次のどちらかを行う。

- `deploy-zen` で artisan を www-data として実行する（例: `sudo -u www-data php artisan migrate --force`）。
- `deploy-zen` の最後に `chown -R www-data:www-data storage/logs` を実行する。

root で動く cron（`schedule:run` など）がある場合も同じ問題が起きるため、www-data の crontab で実行する。

### 影響

- `SESSION_ENCRYPT=true` にすると、既存の暗号化されていないセッションは読めなくなり、セッションは空として扱われる。
  - 「ログイン状態を保存する」を使っているユーザーは、remember Cookie によって自動で再ログインされる。使っていないユーザーはログアウトされる。
  - ゲストの未保存の相談は消える。
  - 切り替え前から開いていたフォームを送信すると、CSRFトークンが一致せず、一度だけ419（ページの有効期限切れ）になる。再読み込みすれば送信できる。
- `APP_KEY` は変更しない。変更すると次のようになる。旧キーを `APP_PREVIOUS_KEYS` に設定すれば、いずれも旧キーで復号できる。
  - セッションと Cookie が読めなくなり、全員がログアウトされる。
  - 保存済み相談の共有トークンを復号できなくなる。この場合、公開URL自体は引き続き閲覧できるが、所有者が「シェア」を押すと新しいURLが発行され、以前のURLは無効になる。
- 以前の方式で自動発行された共有URL（平文キャッシュ）は、有効期限（最大30日）まで閲覧できる。該当する相談の削除時、または新しい共有URLの発行時に無効化される。キャッシュを一括で削除する処理は含まれていない。
- nginx などで既に `Content-Security-Policy` ヘッダーを付与している場合、ブラウザは両方のポリシーを適用する（両方を満たすものだけが許可される）。

### ロールバック

- コードを戻すと、新しい方式で発行した共有URLは404になる（追加したカラムは nullable のため、残っていても旧コードは動作する）。
- `SESSION_ENCRYPT` を `false` に戻すと、再度全ユーザーがログアウトされる。

## 本番での確認コマンド

デプロイ先ディレクトリ（`/var/www/zen`）で実行します。いずれも読み取りのみで、データは変更しません。

| # | 目的 | コマンド | 期待される結果 |
| --- | --- | --- | --- |
| 1 | 環境とデバッグモードの確認 | `php artisan about --only=environment` | `Environment` が `production`、`Debug Mode` が `OFF` |
| 2 | 秘密情報以外の設定値の確認 | `grep -E '^(APP_ENV\|APP_DEBUG\|LOG_CHANNEL\|LOG_STACK\|LOG_LEVEL\|LOG_DAILY_DAYS\|SESSION_DRIVER\|SESSION_ENCRYPT\|SESSION_SECURE_COOKIE\|CACHE_STORE\|DB_CONNECTION)=' .env` | 推奨値の表と一致する（秘密情報のキーは表示されない） |
| 3 | 反映後の設定キャッシュの確認 | `php artisan config:show session.encrypt` | `true` |
| 4 | FPM の php.ini 設定の確認 | `php-fpm8.4 -i \| grep -E 'zend.exception_ignore_args\|opcache.validate_timestamps'` | `zend.exception_ignore_args => On`（Off でもアプリ側で無効化される）。`opcache.validate_timestamps` が `0`/`Off` ならデプロイ後に FPM の reload が必要 |
| 5 | CLI の php.ini 設定の確認 | `php -i \| grep zend.exception_ignore_args` | `On` を推奨 |
| 6 | 既存のCSPとリクエストIDの確認 | `curl -sI https://<本番ドメイン>/ja \| grep -iE 'content-security-policy\|x-request-id'` | デプロイ前は何も出ない想定。デプロイ後は `img-src 'self' data:` と `X-Request-Id` が出る |
| 7 | 既存ログに残った相談文の断片の件数 | `grep -c "respondWithLock(Object(Illuminate/Http/Request), '" storage/logs/*.log` | デプロイ後に件数が増えないこと。既存ログの扱い（ローテーション・削除）は別途判断する |
| 8 | 旧形式の共有キャッシュの件数（参考） | `php artisan tinker --execute 'echo DB::table("cache")->where("key", "like", "%public_consultation_share:%")->where("key", "not like", "%public_consultation_share:v2:%")->count();'` | 件数の確認のみ。最大30日で自然に失効する |
| 9 | マイグレーションの確認 | `php artisan migrate:status \| grep add_share_token_to_zen_consultations` | `Ran` |
| 10 | ログファイルの所有者の確認 | `ls -l storage/logs` | すべてのログファイルの所有者が `www-data`（root のファイルがない） |
