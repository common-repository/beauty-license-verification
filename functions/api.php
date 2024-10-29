<?php

require_once 'db.php';

/**
 * Class BeautyLicenseVerification API.
 */
class BeautyLicenseVerificationAPI
{
    public const URL = 'https://api.licensify.io';

    public const option_name = 'beauticianlist_apikey';

    private static $db      = null;
    private static $key     = [];
    private static $profile = null;

    private static $config  = [];

    private $tag_name       = 'beauticianlist_tag';
    private $params_name    = 'beauticianlist_params';
    private $sellTo_name    = 'beauticianlist_sellTo';

    public function __construct()
    {
        if (!self::$db) {
            self::$db = new BeautyLicenseVerificationDB();
        }
    }

    /**
     * Send api request.
     *
     * @param string $method      GET/POST/PUT/DELETE
     * @param string $path        Route
     * @param array  $data        Params
     * @param bool   $includeAuth Include auth header in request
     *
     * @return array
     *
     * @throws Exception
     */
    private function request($method, $path, $data = [], $includeAuth = false)
    {
        $query = [];
        if ('GET' == $method) {
            $query = array_merge($data, $query);
            $data  = [];
        }

        $query_params = '';
        $params_arr   = [];
        foreach ($query as $k => $v) {
            if (is_array($v)) {
                $arr = [];
                foreach ($v as $key => $value) {
                    $arr[] = $k.'='.$key.'%3D'.$value;
                }

                if ($arr) {
                    $params_arr[] = implode('&', $arr);
                }
            } else {
                $params_arr[] = $k.'='.$v;
            }
        }

        $query_params = implode('&', $params_arr);

        $url          = self::URL;
        $site         = sprintf('%s/%s', $url, $path);

        if ($query_params) {
            $site .= '?'.$query_params;
        }

        $args = [
            'method'        => $method,
            'headers'       => [],
            'body'          => [],
            'timeout'       => '90',
            'redirection'   => '1',
            'httpversion'   => '1.0',
            'blocking'      => true,
            'sslverify'     => false,
        ];

        if ($includeAuth && $token = $this->getSession()) {
            $args['headers']['Authorization'] = 'Bearer '.$token['token'];
        }

        $method = strtoupper($method);
        switch ($method) {
            case 'GET':
                break;

            case 'POST':
            case 'PUT':
            case 'DELETE':
                if ($data) {
                    $data_string = json_encode($data);

                    $args['headers']['Content-Length'] = strlen($data_string);
                    $args['headers']['Content-Type']   = 'application/json';

                    $args['body'] = $data_string;
                } else {
                    $args['headers']['Content-Length'] = 0;
                    $args['headers']['Content-Type']   = 'application/octet-stream';

                    $args['body'] = '';
                }

                break;

            case 'POST_FORM':
                $boundary  = uniqid();
                $delimiter = '-------------'.$boundary;

                $data_string = $this->build_data_files($boundary, $data);

                $args['headers']['Content-Length'] = strlen($data_string);
                $args['headers']['Content-Type']   = 'multipart/form-data; boundary='.$delimiter;

                $args['body'] = $data_string;

                $args['method'] = 'POST';

                break;

            default:
                break;
        }

        $httpCode = false;
        $response = wp_remote_get($site, $args);
        if (is_wp_error($response)) {
            $this->log('errors: '.print_r($response, true));
            $decode = ['message' => 'Failed to connect to BeauticianList API server'];
        } else {
            try {
                $decode   = json_decode($response['body'], JSON_OBJECT_AS_ARRAY);
                $httpCode = $response['response']['code'];
            } catch (Exception $e) {
                $this->log('err: '.print_r($e->getMessage(), true));
                $decode = ['message' => 'Failed to connect to BeauticianList API server'];
            }
        }

        $result = ['success' => false];
        if (200 == $httpCode) {
            $result = ['success' => true, 'data' => $decode];
        } else {
            $result['errors'] = $decode;
            $this->log('errors: '.print_r($decode, true));
        }

        return $result;
    }

    private function build_data_files($boundary, $fields)
    {
        $data = '';
        $eol  = "\r\n";

        $delimiter = '-------------'.$boundary;

        $files = [];
        foreach ($fields as $name => $content) {
            if (is_array($content)) {
                if (!empty($content['name'])) {
                    // detect file
                    $files[$name] = $content;
                    continue;
                }
                // detect array
                $name .= '[]';
                foreach ($content as $row) {
                    $data .= '--'.$delimiter.$eol
                        .'Content-Disposition: form-data; name="'.$name.'"'.$eol.$eol
                        .$row.$eol;
                }
                continue;
            }

            $data .= '--'.$delimiter.$eol
                .'Content-Disposition: form-data; name="'.$name.'"'.$eol.$eol
                .$content.$eol;
        }

        foreach ($files as $name => $content) {
            $data .= '--'.$delimiter.$eol
                 .'Content-Disposition: form-data; name="'.$name.'"; filename="'.$content['name'].'"'.$eol
                 .'Content-Transfer-Encoding: binary'.$eol;
            $data .= $eol;
            $data .= file_get_contents($content['tmp_name']).$eol;
        }
        $data .= '--'.$delimiter.'--'.$eol;

        return $data;
    }

    public function getKeyDetail($key)
    {
        $result = ['success' => false];

        if (empty($key)) {
            $result['error'] = 'Key cannot be empty';

            return $result;
        }

        if (!empty(self::$key)) {
            return self::$key;
        }

        $res = self::request('GET', 'apikey/'.$key, []);
        if (!$res['success']) {
            $result['error'] = $res['errors']['message'];
        } else {
            if ('active' != $res['data']['status']) {
                $result['error'] = 'API Key is not active';

                return $result;
            }

            $result['data']    = $res['data'];
            $result['success'] = true;

            $tags = [$this->params_name => '', $this->tag_name => '', $this->sellTo_name => ''];
            if (!empty($res['data']['params'])) {
                $tags[$this->params_name] = json_encode($res['data']['params']);
            } else {
                if (!empty($res['data']['wp_tag'])) {
                    $tags[$this->tag_name] = $res['data']['wp_tag'];
                }
            }

            $tags[$this->sellTo_name] = json_encode($res['data']['sellToCountries']);

            foreach ($tags as $tag => $tagValue) {
                if (false !== get_option($tag)) {
                    update_option($tag, $tagValue);
                } else {
                    $deprecated = '';
                    $autoload   = 'no';
                    add_option($tag, $tagValue, $deprecated, $autoload);
                }
            }

            self::$key = $result;
        }

        return $result;
    }

    public function history($params = [])
    {
        return self::request('GET', 'apikey/history/'.get_option(self::option_name), $params);
    }

    public function login($email, $password)
    {
        if (!$email || !$password) {
            return ['success' => false, 'errors' => ['message' => 'Please provide email and password']];
        }

        $params = [
            'email'    => $email,
            'password' => $password,
            'apikey'   => get_option(self::option_name),
        ];

        $result = self::request('POST', 'v1.2/authentication/jwt', $params);
        $item   = [
            'status' => 'failed',
            'type'   => 'login',
            'email'  => $email,
        ];

        if ($result['success']) {
            $item['status'] = 'login';
            self::setSession($result['data']);
        }

        return $result;
    }

    public function forgot($email)
    {
        if (!$email) {
            return ['success' => false, 'errors' => ['message' => 'Please provide email']];
        }

        $result = self::request('POST', 'users/reset', ['email' => $email]);
        if ($result['success']) {
            $result['data']['message'] = 'Mail has been sent';
        } else {
            if (!empty($result['errors']) && !empty($result['errors']['message'])) {
                if ('User not found' == $result['errors']['message']) {
                    $result['errors']['message'] = 'A user with this email does not exist';
                }
            }
        }

        return $result;
    }

    public function support($data)
    {
        $result = self::request('POST', 'support', $data);
        if (!$result['success']) {
            $messages = [];
            if (!empty($result['errors']['errors'])) {
                if (!empty($result['errors']['errors']['email']) && 'ValidatorError' == $result['errors']['errors']['email']['code']) {
                    $messages[] = 'Please provide valid email';
                }

                if (!empty($result['errors']['errors']['message']) && 'ValidatorError' == $result['errors']['errors']['message']['code']) {
                    $messages[] = 'Please provide support message';
                }

                if (!empty($result['errors']['errors']['name']) && 'ValidatorError' == $result['errors']['errors']['name']['code']) {
                    $messages[] = 'Please provide name';
                }
            }

            return ['success' => false, 'errors' => ['message' => implode('<br>', $messages)]];
        }

        return $result;
    }

    public function updateFirstStep($data)
    {
        return self::request('POST_FORM', 'license/update', $data);
    }

    public function updateSecondStep($data)
    {
        $result = self::request('POST', 'license/update/step2', $data);
        if ($result['success']) {
            self::setSession($result['data']);
        }

        return $result;
    }

    public function signupSalonFirstStep($data)
    {
        return self::request('POST_FORM', 'v1.1/apiauth/salon/check', $data);
    }

    public function signupFirstStep($data)
    {
        return self::request('POST_FORM', 'v1.5/license/check', $data);
    }

    public function signupSecondStep($data)
    {
        $result = self::request('POST_FORM', 'v1.7/users/register/technician', $data);
        if ($result['success']) {
            $config = $this->getConfig();
            if (in_array(strtolower($data['state']), $config['manual-states'])) {
                $result = [
                    'success' => true,
                    'step'    => 'signupStep2Notify',
                ];
            } else {
                if (array_key_exists('token', $result['data']) && !empty($result['data']['token'])) {
                    self::setSession($result['data']);
                } else {
                    if (array_key_exists('messages', $result['data']) && !empty($result['data']['messages'])) {
                        $result = [
                            'success' => true,
                            'step'    => 'messageNotify',
                            'messages'=> $result['data']['messages'],
                        ];
                    }
                }
            }
        }

        return $result;
    }

    public function signupSalonSecondStep($data)
    {
        $result = self::request('POST_FORM', 'v1.3/users/register/salon', $data);
        if ($result['success']) {
            $config = $this->getConfig();
            if (in_array(strtolower($data['state']), $config['manual-states'])) {
                $result = [
                    'success' => true,
                    'step'    => 'signupStep2Notify',
                ];
            } else {
                if (array_key_exists('token', $result['data']) && !empty($result['data']['token'])) {
                    self::setSession($result['data']);
                } else {
                    if (array_key_exists('messages', $result['data']) && !empty($result['data']['messages'])) {
                        $result = [
                            'success' => true,
                            'step'    => 'messageNotify',
                            'messages'=> $result['data']['messages'],
                        ];
                    }
                }
            }
        }

        return $result;
    }

    public function getSpecialities($data = ['limit' => 0])
    {
        return self::request('GET', 'specialities', $data);
    }

    public function logout()
    {
        $token = $this->getSession();
        if (empty($token['refresh'])) {
            return false;
        }

        $result = self::request('DELETE', 'authentication/jwt', ['refresh' => $token['refresh']], true);
        // if ($result['success']) {
        self::setSession(null);
        // }

        return $result;
    }

    public function getProfile()
    {
        if (!$this->getSession()) {
            return false;
        }

        $result = self::request('GET', 'me/profile', [], true);
        if ($result['success']) {
            if (!in_array($result['data']['role'], ['tech', 'salon'])) {
                self::$profile = null;
                $result        = false;
            } else {
                self::$profile = $result['data'];
            }
        } else {
            self::$profile = null;
            $result        = false;
        }

        return $result;
    }

    public function updateSubscribe($data = [])
    {
        if (!$this->getSession()) {
            return false;
        }

        $res = self::request('PUT', 'me/mailchimp/subscribe', $data, true);
        if ($res['success']) {
            $result = true;
        } else {
            $result = false;
        }

        return $result;
    }

    public function updateTOS()
    {
        if (!$this->getSession()) {
            return false;
        }

        $res = self::request('PUT', 'me/tos', [], true);
        if ($res['success']) {
            $result = true;
        } else {
            $result = false;
        }

        return $result;
    }

    public function checkSession()
    {
        if (!$this->getSession()) {
            return false;
        }

        return true;
    }

    public function getConfig()
    {
        if (empty($this->config)) {
            $result = self::request('GET', 'config');
            if ($result['success']) {
                $this->config = $result['data'];
            }
        }

        return $this->config;
    }

    public function createOrder($id, $data, $order)
    {
        $key = get_option(self::option_name);
        if (!$key) {
            return false;
        }

        $user = get_userdata($order->get_customer_id());

        $order = [
            'fullname'   => $order->get_formatted_billing_full_name(),
            'email'      => $user->user_email,
            'internalId' => '#'.$order->get_id(),
            'price'      => $order->get_total(),
            'customerId' => (string) $user->ID,
        ];

        $res    = false;
        $result = self::request('POST', 'apikey/orders/'.$key, $order);
        if ($result['success']) {
            $res = true;
        }

        return $res;
    }

    private function getSession()
    {
        if (!isset($_COOKIE['BeautyProToken'])) {
            $res = false;
        } else {
            $res = [
                'token'  => sanitize_text_field($_COOKIE['BeautyProToken']),
                'refresh'=> sanitize_text_field($_COOKIE['BeautyProRefresh']),
            ];
        }

        return $res;
    }

    private function setSession($value)
    {
        if ($value) {
            $time = time() + 31556926;
            setcookie('BeautyProToken', $value['token'], $time, '/');
            setcookie('BeautyProRefresh', $value['refresh'], $time, '/');

            $_COOKIE['BeautyProToken']   = $value['token'];
            $_COOKIE['BeautyProRefresh'] = $value['refresh'];
        } else {
            unset($_COOKIE['BeautyProToken']);
            setcookie('BeautyProToken', '', time() - 3600, '/');

            unset($_COOKIE['BeautyProRefresh']);
            setcookie('BeautyProRefresh', '', time() - 3600, '/');
        }

        return true;
    }

    private function log($text = '')
    {
        $file = plugin_dir_path(__FILE__).'../logs';
        /*if (file_exists($file) && 'http://localhost:10010' == self::URL){
            $file .= '/'.\Date('Y-m-d').'.log';
            file_put_contents($file, $text."\n", FILE_APPEND | LOCK_EX);
        }*/
    }

    public function getStates()
    {
        $res = [
            'AL'=> 'Alabama',
            'AK'=> 'Alaska',
            'AS'=> 'American Samoa',
            'AZ'=> 'Arizona',
            'AR'=> 'Arkansas',
            'CA'=> 'California',
            'CO'=> 'Colorado',
            'CT'=> 'Connecticut',
            'DE'=> 'Delaware',
            'FL'=> 'Florida',
            'GA'=> 'Georgia',
            'HI'=> 'Hawaii',
            'ID'=> 'Idaho',
            'IL'=> 'Illinois',
            'IN'=> 'Indiana',
            'IA'=> 'Iowa',
            'KS'=> 'Kansas',
            'KY'=> 'Kentucky',
            'LA'=> 'Louisiana',
            'ME'=> 'Maine',
            'MD'=> 'Maryland',
            'MA'=> 'Massachusetts',
            'MI'=> 'Michigan',
            'MN'=> 'Minnesota',
            'MS'=> 'Mississippi',
            'MO'=> 'Missouri',
            'MT'=> 'Montana',
            'NE'=> 'Nebraska',
            'NV'=> 'Nevada',
            'NH'=> 'New Hampshire',
            'NJ'=> 'New Jersey',
            'NM'=> 'New Mexico',
            'NY'=> 'New York',
            'NC'=> 'North Carolina',
            'ND'=> 'North Dakota',
            'OH'=> 'Ohio',
            'OK'=> 'Oklahoma',
            'OR'=> 'Oregon',
            'PA'=> 'Pennsylvania',
            'RI'=> 'Rhode Island',
            'SC'=> 'South Carolina',
            'SD'=> 'South Dakota',
            'TN'=> 'Tennessee',
            'TX'=> 'Texas',
            'UT'=> 'Utah',
            'VT'=> 'Vermont',
            'VA'=> 'Virginia',
            'WA'=> 'Washington',
            'DC'=> 'Washington D.C.',
            'WV'=> 'West Virginia',
            'WI'=> 'Wisconsin',
            'WY'=> 'Wyoming',
        ];

        return $res;
    }
}
