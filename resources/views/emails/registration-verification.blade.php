<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メールアドレスの確認</title>
    <style>
        body {
            font-family: 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', 'Yu Gothic', Meiryo, sans-serif;
            line-height: 1.8;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #3b82f6;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }
        .button {
            display: inline-block;
            background-color: #3b82f6;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #2563eb;
        }
        .notice {
            background-color: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #6b7280;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
        }
        .link-text {
            word-break: break-all;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>勤怠管理システム</h1>
    </div>
    <div class="content">
        <h2>メールアドレスの確認</h2>

        <p>勤怠管理システムへの新規登録ありがとうございます。</p>
        <p>以下のボタンをクリックして、メールアドレスの確認を完了してください。</p>

        <p style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="button">メールアドレスを確認</a>
        </p>

        <div class="notice">
            <p>このリンクは24時間有効です。</p>
            <p>期限が切れた場合は、再度登録を行ってください。</p>
        </div>

        <p class="link-text">
            ボタンがクリックできない場合は、以下のURLをブラウザにコピー＆ペーストしてください：<br>
            {{ $verificationUrl }}
        </p>

        <div class="footer">
            <p>このメールは自動送信されています。</p>
            <p>このメールに心当たりがない場合は、このメールを無視してください。</p>
        </div>
    </div>
</body>
</html>
