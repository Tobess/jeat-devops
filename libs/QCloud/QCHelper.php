<?php
/**
 * Created by PhpStorm.
 * User: tobess
 * Date: 7/6/18
 * Time: 1:24 AM
 */

namespace QCloud;

class QCHelper
{
    /**
     * 获得青云API地址
     *
     * @param $action
     * @param array $params
     * @return string
     */
    public static function getApiUrl($action, $params = array())
    {
        $params['action'] = $action;
        $params['zone'] = 'pek3b';
        $params['time_stamp'] = now()->setTimezone('UTC')->toIso8601ZuluString();
        $params['access_key_id'] = env('QC_API_KEY_ID');
        $params['version'] = 1;
        $params['signature_method'] = 'HmacSHA256';
        $params['signature_version'] = '1';
        $newParams = [];
        foreach ($params as $key => $val) {
            $newParams[urlencode($key)] = urlencode($val);
        }
        ksort($newParams);

        $queryStr = str_replace('%253A', '%3A', http_build_query($newParams));
        $signStr = 'GET' .  "\n" . '/iaas/' . "\n" . $queryStr;

        $sign = hash_hmac('sha256', $signStr, env('QC_API_KEY_SECRET'), true);
        $sign_b64 = urlencode(base64_encode($sign));

        $queryStr .= ('&signature=' . $sign_b64);


        $baseUrl = 'https://api.qingcloud.com/iaas/?' . $queryStr;

        return str_replace('%253A', '%3A', $baseUrl);
    }
}