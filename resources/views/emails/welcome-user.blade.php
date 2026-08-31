<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アカウント登録完了のお知らせ</title>
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
        .button {
            display: inline-block;
            background-color: #3b82f6;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            margin: 20px 0;
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

        <p>勤怠管理システムへのアカウント登録が完了しました。</p>
        <p>以下のログイン情報をご確認ください。</p>

        <div class="credentials">
            <div class="credentials-item">
                <div class="credentials-label">ログインURL</div>
                <div class="credentials-value">
                    <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
                </div>
            </div>
            <div class="credentials-item">
                <div class="credentials-label">会社コード</div>
                <div class="credentials-value">{{ $companyCode }}</div>
            </div>
            <div class="credentials-item">
                <div class="credentials-label">個人コード</div>
                <div class="credentials-value">{{ $user->employee_code }}</div>
            </div>
            <div class="credentials-item">
                <div class="credentials-label">初期パスワード</div>
                <div class="credentials-value">{{ $plainPassword }}</div>
            </div>
            @if($stampPassword)
            <div class="credentials-item">
                <div class="credentials-label">打刻パスワード</div>
                <div class="credentials-value">{{ $stampPassword }}</div>
            </div>
            @endif
        </div>

        @if($adminLoginUrl)
        <div class="credentials">
            <div class="credentials-item">
                <div class="credentials-label">管理画面ログインURL</div>
                <div class="credentials-value">
                    <a href="{{ $adminLoginUrl }}">{{ $adminLoginUrl }}</a>
                </div>
            </div>
        </div>
        @endif

        <div class="warning">
            <div class="warning-title">重要</div>
            <p>セキュリティのため、初回ログイン時にパスワードの変更が必要です。</p>
            <p>初期パスワードは他の方と共有せず、安全に管理してください。</p>
        </div>

        <a href="{{ $loginUrl }}" class="button">ログインする</a>

        <div class="footer">
            <p>このメールは自動送信されています。</p>
            <p>ご不明な点がございましたら、管理者までお問い合わせください。</p>
        </div>
    </div>
</body>
</html>
