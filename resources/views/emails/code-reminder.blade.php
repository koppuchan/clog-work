<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン情報のお知らせ</title>
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
        .credentials {
            background-color: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .credentials-item {
            margin: 10px 0;
        }
        .credentials-label {
            color: #6b7280;
            font-size: 14px;
        }
        .credentials-value {
            font-weight: bold;
            font-size: 16px;
            color: #111827;
            word-break: break-all;
        }
        .warning {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        .warning-title {
            color: #b45309;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
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
        <p>{{ $user->name }} 様</p>

        <p>ログインに必要なコード情報をお知らせいたします。</p>

        <div class="credentials">
            <div class="credentials-item">
                <div class="credentials-label">会社コード</div>
                <div class="credentials-value">{{ $companyCode }}</div>
            </div>
            <div class="credentials-item">
                <div class="credentials-label">個人コード</div>
                <div class="credentials-value">{{ $user->employee_code }}</div>
            </div>
        </div>

        <div class="warning">
            <div class="warning-title">重要</div>
            <p>このメールにはログイン情報が含まれています。第三者に共有しないでください。</p>
            <p>パスワードを忘れた場合は、ログイン画面の「パスワードを忘れた場合」からリセットしてください。</p>
        </div>

        <p>もしこのメールに心当たりがない場合は、このメールを無視してください。</p>

        <div class="footer">
            <p>このメールは自動送信されています。</p>
            <p>ご不明な点がございましたら、管理者までお問い合わせください。</p>
        </div>
    </div>
</body>
</html>
