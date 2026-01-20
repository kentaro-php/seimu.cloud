<?php
/**
 * Seimu Cloud - 先行リリース登録フォーム処理
 */

header('Content-Type: application/json; charset=utf-8');

// POSTリクエストのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// 入力値の取得とサニタイズ
$council_name = isset($_POST['council_name']) ? trim($_POST['council_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// バリデーション
$errors = [];

if (empty($council_name)) {
    $errors[] = '地方議会名を入力してください';
}

if (empty($email)) {
    $errors[] = 'メールアドレスを入力してください';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '有効なメールアドレスを入力してください';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// メール設定
$admin_email = 'takahashi@dspartners.jp';
$from_email = 'info@dspartners.jp'; // XServerでは実在するメールアドレスが必要
$from_name = 'Seimu Cloud';

// 文字エンコーディング（XServer用に最適化）
mb_language("Japanese");
mb_internal_encoding("UTF-8");

// 日時
$datetime = date('Y年m月d日 H:i:s');

// ========================================
// 管理者への通知メール
// ========================================
$admin_subject = '【Seimu Cloud】先行リリース登録がありました';
$admin_body = <<<EOT
Seimu Cloud 先行リリースの登録がありました。

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
■ 登録情報
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

地方議会名: {$council_name}
メールアドレス: {$email}
登録日時: {$datetime}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

このメールは Seimu Cloud のウェブサイトから自動送信されています。
EOT;

// 管理者へのメールヘッダー（ISO-2022-JP形式）
$admin_headers = "From: " . mb_encode_mimeheader($from_name, 'ISO-2022-JP') . " <{$from_email}>\r\n";
$admin_headers .= "Reply-To: {$email}\r\n";
$admin_headers .= "Content-Type: text/plain; charset=ISO-2022-JP\r\n";
$admin_headers .= "Content-Transfer-Encoding: 7bit\r\n";
$admin_headers .= "X-Mailer: PHP/" . phpversion();

// ========================================
// 登録者への確認メール
// ========================================
$user_subject = '【Seimu Cloud】先行リリースのご登録ありがとうございます';
$user_body = <<<EOT
※このメールは自動送信されています。

{$council_name} 様

Seimu Cloud 先行リリースにご登録いただき、
誠にありがとうございます。

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
■ ご登録内容
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

地方議会名: {$council_name}
メールアドレス: {$email}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

サービス開始時には、改めてご連絡させていただきます。
今しばらくお待ちくださいますようお願い申し上げます。

ご不明な点がございましたら、下記までお問い合わせください。

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Seimu Cloud（政務クラウド）
DS Partners Investment Inc.

Email: info@dspartners.jp
URL: https://seimu.cloud
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EOT;

// 登録者へのメールヘッダー（ISO-2022-JP形式）
$user_headers = "From: " . mb_encode_mimeheader($from_name, 'ISO-2022-JP') . " <{$from_email}>\r\n";
$user_headers .= "Reply-To: info@dspartners.jp\r\n";
$user_headers .= "Content-Type: text/plain; charset=ISO-2022-JP\r\n";
$user_headers .= "Content-Transfer-Encoding: 7bit\r\n";
$user_headers .= "X-Mailer: PHP/" . phpversion();

// メール送信（件名と本文をISO-2022-JPに変換）
$admin_sent = mb_send_mail(
    $admin_email,
    mb_encode_mimeheader($admin_subject, 'ISO-2022-JP'),
    mb_convert_encoding($admin_body, 'ISO-2022-JP', 'UTF-8'),
    $admin_headers
);

$user_sent = mb_send_mail(
    $email,
    mb_encode_mimeheader($user_subject, 'ISO-2022-JP'),
    mb_convert_encoding($user_body, 'ISO-2022-JP', 'UTF-8'),
    $user_headers
);

// 結果を返す
if ($admin_sent && $user_sent) {
    // 送信成功をログに記録
    error_log("Seimu Cloud - Mail sent successfully: email={$email}, council={$council_name}");
    echo json_encode(['success' => true, 'message' => '登録が完了しました']);
} else {
    // 詳細なエラー情報をログに記録
    $error_detail = "Seimu Cloud - Mail send failed:\n";
    $error_detail .= "Admin mail (to: {$admin_email}): " . ($admin_sent ? 'SUCCESS' : 'FAILED') . "\n";
    $error_detail .= "User mail (to: {$email}): " . ($user_sent ? 'SUCCESS' : 'FAILED') . "\n";
    $error_detail .= "Council: {$council_name}\n";
    $error_detail .= "From: {$from_email}";
    error_log($error_detail);
    
    // どちらか一方でも失敗した場合
    if (!$admin_sent) {
        echo json_encode(['success' => false, 'message' => '管理者へのメール送信に失敗しました']);
    } else {
        echo json_encode(['success' => false, 'message' => '確認メールの送信に失敗しました']);
    }
}
