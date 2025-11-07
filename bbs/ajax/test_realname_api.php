<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => '未登录']);
}

$user = getCurrentUser();
if (!$user || $user['level'] < 8) {
    jsonResponse(['success' => false, 'message' => '权限不足']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '无效的请求方法']);
}

$name = escape($_POST['test_name'] ?? '');
$idcard = escape($_POST['test_idcard'] ?? '');

if (empty($name) || empty($idcard)) {
    jsonResponse(['success' => false, 'message' => '请填写姓名和身份证号']);
}

// 获取阿里云配置
$appcode = getSystemSetting('aliyun_appcode', '');
$appkey = getSystemSetting('aliyun_appkey', '');
$appsecret = getSystemSetting('aliyun_appsecret', '');

if (empty($appcode) || empty($appkey) || empty($appsecret)) {
    jsonResponse(['success' => false, 'message' => '请先配置阿里云API凭证']);
}

// 调用阿里云实名认证API（示例代码）
try {
    // 这里应该是实际的API调用代码
    // 由于阿里云API的具体实现需要根据购买的服务来定，这里提供示例
    
    $api_url = "https://edis3.market.alicloudapi.com/idcard/verify";
    $params = [
        'name' => $name,
        'idcard' => $idcard
    ];
    
    $headers = [
        "Authorization: APPCODE " . $appcode,
        "Content-Type: application/x-www-form-urlencoded; charset=UTF-8"
    ];
    
    // 使用cURL调用API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        
        if (isset($result['status']) && $result['status'] === '01') {
            jsonResponse(['success' => true, 'message' => '身份信息验证通过']);
        } else {
            jsonResponse(['success' => false, 'message' => '身份信息验证失败：' . ($result['msg'] ?? '未知错误')]);
        }
    } else {
        jsonResponse(['success' => false, 'message' => 'API请求失败，HTTP代码：' . $http_code]);
    }
    
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => '接口调用异常：' . $e->getMessage()]);
}

// 模拟成功响应（用于测试）
jsonResponse(['success' => true, 'message' => '测试模式：身份信息验证通过（模拟响应）']);
?>