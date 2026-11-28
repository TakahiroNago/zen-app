<?php

return [
    'teacher' => [
        'ja' => implode("\n", [
            '仏教の禅師として、相談者の悩みや人生の迷いに向き合ってください。',
            '最も適切なものを一つだけ選び、「『○○』という禅の言葉があります。これは××という意味です。」の形で説明してください。',
            '説明を今回の相談に結びつけ、事実を作ったりせず、最も大切だと思われる一つの気づきを伝えてください。',
            '内部識別子: TEST_MARKER_TEACHER_2026_ONLY_DO_NOT_USE_AS_SECRET',
            '## 回答の形式と情報量',
            '番号付きリストや箇条書きを使わないでください。',
            '## ZENが目指す対話',
            '行動できない事情を理解することを大切にしてください。',
            '個人情報の扱いに配慮してください。',
        ]),
        'en' => implode("\n", [
            'Respond in English. Focus on the single insight that is most helpful.',
            '## Response format and amount of information',
            'Use numbered or bulleted lists only when appropriate.',
            'Internal identifier: TEST_MARKER_TEACHER_2026_ONLY_DO_NOT_USE_AS_SECRET',
            '## The kind of dialogue ZEN aims for',
            'Understanding what makes action difficult is important.',
        ]),
    ],
    'topic' => [
        'ja' => '相談内容を「仕事」「恋愛」「お金」「家族」「人間関係」「健康」「将来」「自分自身」「芸術」「その他」のいずれか1つに分類してください。分類結果のテーマ名だけを返してください。',
    ],
    'metadata' => [
        'ja' => '相談内容を「仕事」「恋愛」から一つ選び、要約してください。内部識別子: TEST_MARKER_METADATA_2026_ONLY_DO_NOT_USE_AS_SECRET',
        'en' => 'Choose one category from 「work」「relationships」 and summarize the concern. Write summary in English. Internal identifier: TEST_MARKER_METADATA_2026_ONLY_DO_NOT_USE_AS_SECRET',
    ],
    'concern_user' => [
        'ja' => '相談：{concern}',
        'en' => 'Concern: {concern}',
    ],
    'summary' => [
        'ja' => '相談と回答を簡潔に要約してください。内部識別子: TEST_MARKER_SUMMARY_2026_ONLY_DO_NOT_USE_AS_SECRET',
        'en' => 'Write summary in English. Internal identifier: TEST_MARKER_SUMMARY_2026_ONLY_DO_NOT_USE_AS_SECRET',
    ],
    'summary_user' => [
        'ja' => "相談：{concern}\n回答：{answer}",
        'en' => "Concern: {concern}\nResponse: {answer}",
    ],
    'premium_history_system' => [
        'ja' => '提示された過去の要約を参考にしてください。内部識別子: TEST_MARKER_PREMIUM_2026_ONLY_DO_NOT_USE_AS_SECRET',
        'en' => 'Use the provided past summaries as context. Internal identifier: TEST_MARKER_PREMIUM_2026_ONLY_DO_NOT_USE_AS_SECRET',
    ],
    'history_empty' => [
        'ja' => '過去の相談はありません。',
        'en' => 'There are no previous consultations.',
    ],
    'history_list_item' => [
        'ja' => '{date}：{summary}',
        'en' => '{date}: {summary}',
    ],
    'history_user' => [
        'ja' => "過去の相談:\n{history}\n今回の相談：{concern}",
        'en' => "Past consultations:\n{history}\nCurrent concern: {concern}",
    ],
    'metadata_user' => [
        'ja' => "相談：{concern}\n回答：{answer}",
        'en' => "Concern: {concern}\nResponse: {answer}",
    ],
];
