<?php

/**
 * Class BeautyLicenseVerification Service.
 */
class BeautyLicenseVerificationService
{
    private $params_name          = 'beauticianlist_params';
    private $option_google_name   = 'beauticianlist_google_key';
    private $option_google_secret = 'beauticianlist_google_secret';

    public function getProfileField($value)
    {
        $str = '
            <h3>BeauticianList Information</h3> 
            <table class="form-table">
                <tr>
                    <th><label for="bl_tag">Tag</label></th>
                    <td>
                        <input type="text" name="bl_tag" id="bl_tag" value="'.$value.'" class="regular-text" /><br/>
                        <span></span>
                    </td>
                </tr>
            </table>
        ';

        return $str;
    }

    public function getShortCodeButtton($profile, $params, $atts)
    {
        $initAtts = $this->formatAtts($params, $atts);

        $stylesArr = [
            'border-bottom: 1px solid '.$initAtts['color'],
            'border-radius: 2px',
            'background-color: '.$initAtts['background-color'],
            'color: '.$initAtts['color'],
            'font-size: '.$initAtts['font-size'],
        ];

        $styles = implode('; ', $stylesArr);
        if ($profile) {
            $fname = '';
            if (!empty($profile['data']['profile']['firstname'])) {
                $fname = $profile['data']['profile']['firstname'];
            }

            $lname  = $profile['data']['profile']['lastname'];
            $nonce  = wp_create_nonce('beauty-pro-logout');
            $logout = '<a data-nonce="'.$nonce.'" data-href="'.admin_url('admin-ajax.php').'" data-element="beauty_pro_logout" href="#">Logout</a>';

            $str = '
                <div style="text-align: '.$initAtts['alignment'].';">
                    <button type="button" style="'.$styles.'">
                        <span data-node="label">Logged in as '.$fname.' '.$lname.'. '.$logout.'</span>
                    </button>
                </div>';

            if (empty($profile['data']['tos'])) {
                $str .= $this->getTOSPopup();
            }

            $str .= $this->getLogoutPopup();
        } else {
            $str = '
                <div style="text-align: '.$initAtts['alignment'].';">
                    <button type="button" data-toggle="modal" data-target="#beauty_pro_signup_login_popup" style="'.$styles.'">
                        <span data-node="label">'.$initAtts['text'].'</span>
                    </button>
                </div>';
        }

        return $str;
    }

    private function formatAtts($params, $atts)
    {
        $initAtts = [
            'alignment'        => 'center',
            'text'             => 'BEAUTY PRO',
            'background-color' => '#ff0000',
            'color'            => '#ffffff',
            'font-size'        => '14px',
        ];

        if ($params) {
            $paramsAll = [];
            try {
                $paramsAll = json_decode($params, true);
            } catch (Exception $e) {
            }

            if (!empty($paramsAll['beauty_pro_only_text'])) {
                $initAtts['text'] = $paramsAll['beauty_pro_only_text'];
            }
        }

        if ($atts) {
            foreach ($atts as $key => $att) {
                $newKey = sanitize_text_field($key);
                if (array_key_exists($newKey, $initAtts)) {
                    $initAtts[$newKey] = sanitize_text_field(sanitize_text_field($att));
                }
            }
        }

        return $initAtts;
    }

    public function getSingleButton($product, $BLProfile, $defaultAddAction, $buttonURL, $buttonText)
    {
        $str = '
            <form class="cart cart-" action="'.get_permalink($product->get_id()).'" method="POST" enctype="multipart/form-data">
                <div class="quantity">
                    <label class="screen-reader-text" for="quantity_5db9cfba18d3d">'.$product->get_name().' quantity</label>
                    <input type="number" id="quantity_5db9cfba18d3d" class="input-text qty text" step="1" min="1" max="" name="quantity" value="1" title="Qty" size="4" inputmode="numeric">
                </div>';
        if (!$BLProfile) {
            if (!$defaultAddAction) {
                $str .= '<div class="">';
                if ($buttonURL) {
                    $str .= '<button type="button" data-href="'.$buttonURL.'" data-element="beauty_pro_link" class="button">'.$buttonText.'</button>';
                } else {
                    $str .= '<button type="button" data-toggle="modal" data-target="#beauty_pro_signup_login_popup" class="button">'.$buttonText.'</button>';
                }

                $str .= '</div>';
            } else {
                $str .= '<button type="submit" name="add-to-cart" value="'.esc_attr($product->get_id()).'" class="single_add_to_cart_button button alt">Add to cart</button>';
            }
            $str .= '</form>';
        } else {
            if (!$defaultAddAction) {
                $str .= '<button type="submit" name="add-to-cart" value="'.esc_attr($product->get_id()).'" class="single_add_to_cart_button button alt">Add to cart</button>';
            } else {
                $str .= '<button type="submit" name="add-to-cart" value="'.esc_attr($product->get_id()).'" class="single_add_to_cart_button button alt">Add to cart</button>';
            }
            $nonce = wp_create_nonce('beauty-pro-logout');
            $str .= '
                </form>
                <br>
                <p>You are logged in as '.$BLProfile['data']['email'].'<p>
                <button type="button" data-nonce="'.$nonce.'" data-href="'.admin_url('admin-ajax.php').'" data-element="beauty_pro_logout" class="button ">Logout</button>
            ';

            if (empty($BLProfile['data']['tos'])) {
                $str .= $this->getTOSPopup();
            }

            $str .= $this->getLogoutPopup();
        }

        return $str;
    }

    private function getTOSPopup()
    {
        $nonceTOS = wp_create_nonce('beauty-pro-tos');
        $str      = '
            <!-- Modal -->
            <div class="bootstrap-bl">
                <div class="modal fade beauty_pro_popup blist__wrapper" id="beauty_pro_popup" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="blist__dialog modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-body m-0 p-0">
                                <div class="blist__dialog__main container-fluid">
                                    <header class="blist__dialog__header modal-header">
                                        <div class="blist__dialog__close" data-dismiss="modal" aria-label="Close"></div>
                                        <h1>License Verification via</h1>
                                        <div class="blist__dialog__logo">
                                            <div class="blist__svg blist__svg--logo" title="Licensify"></div>
                                        </div>
                                    </header>

                                    <!-- TOS step -->
                                    <div data-element="beauty_pro_intro_div">
                                        <div class="blist__form__header">
                                            <p>Please update following information.</p>
                                        </div>
                                        <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                                <input type="hidden" name="action" value="beauty_pro_tos">
                                                <input type="hidden" name="nonce" value="'.$nonceTOS.'">
                                            <div class="form-group">
                                                <div class="blist__check">
                                                    <label class="blist__check__label" for="subscribeTOS">
                                                        <input class="blist__input blist__input--check" type="checkbox" name="subscribe" id="subscribeTOS">                                        	
                                                        <span class="blist__check__text">Subscribe to email updates</span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="blist__check">
                                                    <label class="blist__check__label" for="subscribeAdditionalTOS">
                                                        <input class="blist__input blist__input--check" type="checkbox" name="subscribeAdditional" id="subscribeAdditionalTOS">                                        	
                                                        <span class="blist__check__text">Opt in to our third party list to receive relevant offers from our sponsors and partners</span>
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <div class="blist__check">
                                                    <label class="blist__check__label" for="confirmTOS">
                                                        <input class="blist__input blist__input--check" type="checkbox" name="confirm" id="confirmTOS">
                                                        <span class="blist__check__text">I have read and understand the <a href="https://beauticianlist.com/pages/disclaimer-licensedbeautician" target="_blank">disclaimer</a> *</span>
                                                    </label>
                                                </div>
                                                <small class="invalid-message display-none" data-element="beauty_pro_tos_error_confirmTOS"></small>
                                            </div>
                                            <button type="button" class="blist__button blist__button--primary is-large" data-element="beauty_pro_tos">Update my data</button>
                                        </form>     
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        ';

        return $str;
    }

    private function getLogoutPopup()
    {
        $nonceTOS = wp_create_nonce('beauty-pro-logout');
        $str      = '
            <!-- Modal -->
            <div class="bootstrap-bl">
                <div class="modal fade beauty_pro_popup blist__wrapper" id="beauty_pro_logout_popup" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="blist__dialog modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-body m-0 p-0">
                                <div class="blist__dialog__main container-fluid">
                                    <header class="blist__dialog__header modal-header">
                                        <div class="blist__dialog__close" data-dismiss="modal" aria-label="Close"></div>
                                        <h1>License Verification via</h1>
                                        <div class="blist__dialog__logo">
                                            <div class="blist__svg blist__svg--logo" title="Licensify"></div>
                                        </div>
                                    </header>

                                    <!-- Logout step -->
                                    <div data-element="beauty_pro_intro_div">
                                        <div class="blist__form__header">
                                            <p>If you sign out now, in order to make a purchase in the future, you\'ll need to sign back into your BeautyAList profile.</p>
                                        </div>
                                        <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                            <input type="hidden" name="action" value="beauty_pro_logout">
                                            <input type="hidden" name="nonce" value="'.$nonceTOS.'">
                                            <div class="form-row">
                                                <div class="col">
                                                    <button type="button" class="blist__button blist__button--primary is-large" data-dismiss="modal">Close</button>
                                                </div>
                                                <div class="col">
                                                    <button type="button" class="blist__button blist__button--primary is-large" data-element="beauty_pro_logout_action">Logout</button>
                                                </div>
                                            </div>
                                        </form>     
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        ';

        return $str;
    }

    public function checkUserTags()
    {
        global $current_user;
        $state = true;
        if (is_user_logged_in()) {
            $cuser = wp_get_current_user();

            $userTags = [];
            if ($v = get_user_meta($cuser->ID, 'bl_tag', true)) {
                if (get_option($this->params_name)) {
                    $v = explode(',', $v);
                    foreach ($v as $value) {
                        $userTags[] = trim($value);
                    }
                }
            }

            $params = [];
            if (get_option($this->params_name)) {
                try {
                    $params = json_decode(get_option($this->params_name), true);
                } catch (Exception $e) {
                }

                if (!empty($params['default_register_tag'])) {
                    if (!in_array($params['default_register_tag'], $userTags)) {
                        $state = false;
                    }
                }
            }
        } else {
            $state = false;
        }

        return $state;
    }

    public function return_bytes($val)
    {
        $val  = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val  = str_replace($val[strlen($val) - 1], '', $val);
        switch ($last) {
            case 'g':
            $val *= 1024;
            // no break
            case 'm':
            $val *= 1024;
            // no break
            case 'k':
            $val *= 1024;
        }

        return $val;
    }

    public function max_file_upload_in_bytes()
    {
        // select maximum upload size
        $max_upload = $this->return_bytes(ini_get('upload_max_filesize'));
        // select post limit
        $max_post = $this->return_bytes(ini_get('post_max_size'));
        // select memory limit
        $memory_limit = $this->return_bytes(ini_get('memory_limit'));

        // return the smallest of them, this defines the real limit
        return min($max_upload, $max_post, $memory_limit);
    }

    public function verifyCaptcha($post)
    {
        $result    = true;
        $key       = get_option($this->option_google_name);
        $secret    = get_option($this->option_google_secret);
        if (!empty($key) && !empty($secret)) {
            if (!isset($post['g-recaptcha-response'])) {
                $result = false;
            } else {
                $url      = 'https://www.google.com/recaptcha/api/siteverify';
                $params   = [
                    'secret'   => $secret,
                    'response' => isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '',
                    'remoteip' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'],
                ];
                $response = wp_remote_get(add_query_arg($params, $url));
                if (is_wp_error($response) || empty($response['body']) || !($json = json_decode($response['body'])) || !$json->success) {
                    $result = false;
                }
            }
        }

        return $result;
    }
}
