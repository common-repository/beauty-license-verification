<?php

require_once 'api.php';
require_once 'service.php';

/**
 * Class BeautyLicenseVerification (Signup / Login).
 */
class BLVSignupLogin
{
    private $option_name = 'beauticianlist_apikey';
    private $params_name = 'beauticianlist_params';
    private $sellTo_name = 'beauticianlist_sellTo';
    private $tag_name    = 'beauticianlist_tag';
    private $url_name    = 'beauticianlist_url';

    private $option_google_name   = 'beauticianlist_google_key';
    private $option_google_secret = 'beauticianlist_google_secret';

    private $service;

    /**
     * Constructor.
     */
    public function __construct()
    {
        if (!is_admin()) {
            add_filter('script_loader_tag', [$this, 'add_asyncdefer_attribute'], 10, 2);
        }
        // Added js and css
        add_action('wp_enqueue_scripts', [$this, 'added_scripts']);

        // Replacing add-to-cart button in Single product pages
        add_action('woocommerce_single_product_summary', [$this, 'removing_addtocart_buttons'], 10);

        $actions = [
            'login',
            'forgot',
            'support',
            'international',
            'logout',
            'verification',
            'verification_support',
            'salon_verification',
            'salon_verification_support',
            'verification_step2',
            'update',
            'update_step2',
            'tos',
        ];
        foreach ($actions as $action) {
            add_action('wp_ajax_nopriv_beauty_pro_'.$action, [$this, 'handle_form_'.$action.'_submit']);
            add_action('wp_ajax_beauty_pro_'.$action, [$this, 'handle_form_'.$action.'_submit']);
        }

        add_action('wp_default_scripts', function ($scripts) {
            if (!empty($scripts->registered['jquery'])) {
                $scripts->registered['jquery']->deps = array_diff($scripts->registered['jquery']->deps, ['jquery-migrate']);
            }
        });

        // Inject login signup popup
        add_action('wp_footer', [$this, 'add_content_to_footer']);

        // Remove add to cart button on products list
        add_action('woocommerce_product_single_add_to_cart_text', [$this, 'remove_add_to_cart_buttons'], 1);

        // Change product button to read more
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'replacing_add_to_cart_button'], 10, 2);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'avoid_add_to_cart_conditionally'], 10, 3);

        // Add meta key to order
        add_action('woocommerce_checkout_order_processed', [$this, 'checkout_create_order'], 10, 3);

        add_filter('woocommerce_variable_sale_price_html', [$this, 'remove_prices'], 10, 2);
        add_filter('woocommerce_variable_price_html', [$this, 'remove_prices'], 10, 2);
        add_filter('woocommerce_get_price_html', [$this, 'remove_prices'], 10, 2);

        // Added button shortcode
        add_shortcode('beauty-license-verification-button', [$this, 'shortcode_button']);

        // Added tag for new user registartion
        add_action('user_register', [$this, 'user_register'], 10, 1);

        // Added new field on prodfile
        add_action('edit_user_profile', [$this, 'be_show_extra_profile_fields']);

        // Checked permission for tag update
        add_action('personal_options_update', [$this, 'be_save_extra_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'be_save_extra_profile_fields']);

        $this->service = new BeautyLicenseVerificationService();
    }

    /**
     * Added js and css.
     */
    public function added_scripts()
    {
        $dir     =  plugin_dir_url(__FILE__);
        $enable  = false;
        $version = '1.0.35';

        if (class_exists('WooCommerce')) {
            if (!is_checkout() && !is_cart()) {
                $enable = true;
            }
        } else {
            $enable = true;
        }

        if ($enable) {
            $key       = get_option($this->option_google_name);
            $secret    = get_option($this->option_google_secret);
            if (!empty($key) && !empty($secret)) {
                $url = 'https://www.google.com/recaptcha/api.js?render=explicit';
                wp_enqueue_script('blv-plugin-script-captcha-async-defer', $url, [], $version);
            }

            $path = plugins_url('js/script.js', __FILE__);
            wp_register_script('blv-plugin-script', $path, ['jquery', 'jquery-ui-autocomplete'], filemtime(dirname(__FILE__).'/js/script.js'));
            wp_enqueue_script('blv-plugin-script');

            wp_enqueue_script('blv-plugin-script-bootstrap', $dir.'js/assets/bootstrap.min.js', [], $version);

            wp_enqueue_style('blv-plugin-style-bootstrap-io', $dir.'css/bootstrap-iso.css', false, $version);
            wp_enqueue_style('blv-plugin-style', $dir.'css/styles.css', false, $version);
            wp_enqueue_style('blv-plugin-style-overlay', $dir.'css/overlay.css', false, $version);
        }
    }

    public function add_asyncdefer_attribute($tag, $handle)
    {
        // if the unique handle/name of the registered script has 'async' in it
        if (false !== strpos($handle, 'async')) {
            // return the tag with the async attribute
            $tag = str_replace('<script ', '<script async ', $tag);
        }
        // if the unique handle/name of the registered script has 'defer' in it
        if (false !== strpos($handle, 'defer')) {
            // return the tag with the defer attribute
            $tag = str_replace('<script ', '<script defer ', $tag);
        }
        // otherwise skip

        return $tag;
    }

    public function wc_product_add_to_cart_text($text, $product)
    {
        $api = new BeautyLicenseVerificationAPI();
        if (!$api->checkSession()) {
            $tags = wp_get_object_terms($product->get_id(), 'product_tag');
            foreach ($tags as $tag) {
                if ('beauty-pro' == $tag->slug) {
                    // $text = 'Read more';
                }
            }
        }

        return $text;
    }

    public function replacing_add_to_cart_button($button, $product)
    {
        $api = new BeautyLicenseVerificationAPI();
        if (!$api->checkSession() && has_term(['beauty-pro'], 'product_tag', $product->get_id())) {
            $button = '';
        }

        return $button;
    }

    public function avoid_add_to_cart_conditionally($passed, $product_id, $quantity)
    {
        $api = new BeautyLicenseVerificationAPI();
        if (has_term(['beauty-pro'], 'product_tag', $product_id)) {
            if (!$api->checkSession()) {
                if (!$this->service->checkUserTags()) {
                    wc_add_notice('Please login / signup as Beauty pro user on product page ', 'error');
                    $passed = false;
                }
            }
        }

        return $passed;
    }

    public function single_product_custom_button()
    {
        global $product;

        $api       = new BeautyLicenseVerificationAPI();
        $BLProfile = $api->getProfile();
        $keyStatus = true;

        $key = get_option($this->option_name);
        $res = $api->getKeyDetail($key);

        if (!$res['success']) {
            $keyStatus = false;
        }

        $defaultAddAction = true;
        if ($keyStatus) {
            $tags = wp_get_object_terms($product->get_id(), 'product_tag');
            foreach ($tags as $tag) {
                if ('beauty-pro' == $tag->slug) {
                    $defaultAddAction = false;
                }
            }
        }

        $buttonText = 'Beauty pro only';
        $buttonURL  = '';
        $params     = [];
        if (get_option($this->params_name)) {
            try {
                $params = json_decode(get_option($this->params_name), true);
            } catch (Exception $e) {
            }

            if (!empty($params['beauty_pro_only_text'])) {
                $buttonText = $params['beauty_pro_only_text'];
            }

            if (!empty($params['button_url'])) {
                $buttonURL = $params['button_url'];
            }
        }

        if (!$buttonURL && get_option($this->url_name)) {
            $buttonURL = get_option($this->url_name);
        }

        if (!$BLProfile) {
            if (!$defaultAddAction) {
                global $current_user;
                if (is_user_logged_in()) {
                    $cuser    = wp_get_current_user();
                    $userTags = [];
                    if ($v = get_user_meta($cuser->ID, 'bl_tag', true)) {
                        if (get_option($this->params_name)) {
                            $v = explode(',', $v);
                            foreach ($v as $value) {
                                $userTags[] = trim($value);
                            }
                        }
                    }

                    if (!empty($params['default_register_tag'])) {
                        if (in_array($params['default_register_tag'], $userTags)) {
                            $defaultAddAction = true;
                        }
                    }
                }
            }
        }

        echo $this->service->getSingleButton($product, $BLProfile, $defaultAddAction, $buttonURL, $buttonText);
    }

    public function removing_addtocart_buttons()
    {
        remove_action('woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', 30);
        add_action('woocommerce_simple_add_to_cart', [$this, 'single_product_custom_button'], 30);
    }

    public function remove_prices($price, $product)
    {
        $api  = new BeautyLicenseVerificationAPI();
        $tags = wp_get_post_terms($product->get_id(), 'product_tag');
        foreach ($tags as $tag) {
            if ('beauty-pro' == $tag->slug) {
                if (!$api->checkSession()) {
                    if (!$this->service->checkUserTags()) {
                        $price = '';
                    }
                }
            }
        }

        return $price;
    }

    public function add_content_to_footer()
    {
        $api    = new BeautyLicenseVerificationAPI();
        $config = $api->getConfig();

        $allSpecialities = [];
        $specialities    = $api->getSpecialities();
        foreach ($specialities['data'] as $speciality) {
            $allSpecialities[$speciality['group']][$speciality['id']] = $speciality['title'];
        }

        $t = '';
        foreach ($allSpecialities as $group => $items) {
            $t .= '<optgroup label="'.$group.'">'.$group.'</optgroup>';
            foreach ($items as $id => $title) {
                $t .= '<option value="'.$id.'">'.$title.'</option>';
            }
        }

        /* Specialities for signup */
        $specialitiesStr = $t;
        /* Specialities for update */
        $specialitiesUpdateStr = $t;

        $nonce                    = wp_create_nonce('beauty-pro-login');
        $nonceForgot              = wp_create_nonce('beauty-pro-forgot');
        $nonceSupport             = wp_create_nonce('beauty-pro-support');
        $nonceInternational       = wp_create_nonce('beauty-pro-international');
        $nonceVerification        = wp_create_nonce('beauty-pro-verification');
        $nonceSalonVerification   = wp_create_nonce('beauty-pro-salon-verification');
        $nonceVerificationStep1_1 = wp_create_nonce('beauty-pro-verification-step1_1');
        $nonceVerificationStep2   = wp_create_nonce('beauty-pro-verification-step2');
        $nonceUpdate              = wp_create_nonce('beauty-pro-update');
        $nonceUpdateStep1_1       = wp_create_nonce('beauty-pro-update-step1_1');
        $nonceUpdateStep2         = wp_create_nonce('beauty-pro-update-step2');

        $source     = htmlspecialchars(json_encode($config['strings']['signup-licenseno']));

        $fullStates = $api->getStates();
        $states     = htmlspecialchars(json_encode($fullStates));

        $countries = [];
        $sellTo    = get_option($this->sellTo_name);
        if ($sellTo) {
            try {
                $countries = json_decode($sellTo, true);
            } catch (Exception $e) {
            }
        }

        $countryElement = '';
        $hideStates     = true;
        if (1 == count($countries)) {
            $countryElement = '<input type="hidden" data-element="beauty_pro_verification_country" name="country" value="'.$countries[0].'">';
            if ('usa' == $countries[0]) {
                $hideStates = false;
            }
        } else {
            $all            = ['usa' => 'USA', 'canada' => 'Canada', 'other' => 'Other countries'];
            $countryElement = '<div class="form-group"><select class="form-control" data-element="beauty_pro_verification_country" name="country">';
            foreach ($countries as $country) {
                if ('usa' == $country) {
                    $hideStates = false;
                }
                $countryElement .= '<option value="'.$country.'">'.$all[$country].'</option>';
            }
            $countryElement .= '</select></div>';
        }

        $stateElement = '';
        if (!$hideStates) {
            $stateElement = '<div class="form-group">
                <input class="form-control" name="state" data-element="beauty_pro_verification_state" data-source="'.$states.'" placeholder="State *">
                <small class="invalid-message display-none" data-element="beauty_pro_verification_error_state"></small>
                <p class="blist__field__hint"  data-element="beauty_pro_verification_hint_state"></p>
            </div>';
        }

        $maxFileSize  = '';
        $configOption = $this->service->max_file_upload_in_bytes();
        if ($configOption) {
            $max         = $configOption / 1024 / 1024;
            $maxFileSize = '<small>The maximum allowed file size is '.$max.'MB.</small>';
        }

        $configParams = ['manual-states' => $config['manual-states']];

        $registrationTechCaptcha = $registrationSalonCaptcha = $supportCaptcha = $internationalCaptcha = '';
        $key                     = get_option($this->option_google_name);
        $secret                  = get_option($this->option_google_secret);
        if (!empty($key) && !empty($secret)) {
            $registrationTechCaptcha  = '<div class="form-group"><div id="registration_tech_captcha"></div></div>';
            $registrationSalonCaptcha = '<div class="form-group"><div id="registration_salon_captcha"></div></div>';
            $supportCaptcha           = '<div class="form-group"><div id="support_captcha"></div></div>';
            $internationalCaptcha     = '<div class="form-group"><div id="international_captcha"></div></div>';

            $configParams['captcha-key'] = $key;
        }

        $dataConfig = htmlspecialchars(json_encode($configParams));

        $str = '
            <!-- Modal -->
            <div class="bootstrap-bl">
                <div class="modal fade beauty_pro_popup blist__wrapper" id="beauty_pro_signup_login_popup" data-config="'.$dataConfig.'" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="blist__dialog modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-body flex-content m-0 p-0">
                                <div class="blist__dialog__aside">
                                    <div class="blist__aside">
                                        <div class="blist__aside__title">
                                            <h2 class="h1">Benefits</h2>
                                        </div>
                                        <section class="blist__aside__item">
                                            <div class="blist__aside__label">
                                                <div class="blist__svg blist__svg--shop"></div>
                                                <h2>Shop</h2>
                                            </div>
                                            <p class="is-small">Unlock exclusive access to professional-only products and enjoy special pricing with our trusted e-commerce partners.</p>
                                        </section>
                                        <section class="blist__aside__item">
                                            <div class="blist__aside__label">
                                                <div class="blist__svg blist__svg--grow"></div>
                                                <h2>Grow</h2>
                                            </div>
                                            <p class="is-small">Improve your visibility and showcase your unique skills by updating your profile details and uploading pictures of your work. Expand your clientele and attract new opportunities by highlighting your areas of expertise.</p>
                                        </section>
                                        <section class="blist__aside__item">
                                            <div class="blist__aside__label">
                                                <div class="blist__svg blist__svg--learn"></div>
                                                <h2>Learn</h2>
                                            </div>
                                            <p class="is-small">Gain industry insights with exclusive content, including product launches, advice from industry leaders, and State Board news.</p>
                                        </section>
                                        <section class="blist__aside__item">
                                            <div class="blist__aside__label">
                                                <div class="blist__svg blist__svg--qualify"></div>
                                                <h2>Qualify</h2>
                                            </div>
                                            <p class="is-small">Receive timely reminders for license expiration with our platform. We provide reminders three months and one month before your license expires, ensuring you stay on top of your professional obligations.</p>
                                        </section>
                                    </div>
                                </div>
                                <div class="blist__dialog__main container-fluid">
                                    <header class="blist__dialog__header modal-header">
                                        <div class="blist__dialog__close" data-dismiss="modal" aria-label="Close"></div>
                                        <h1>License Verification via</h1>
                                        <div class="blist__dialog__logo">
                                            <div class="blist__svg blist__svg--logo" title="Licensify"></div>
                                        </div>
                                    </header>

                                    <!-- Intro step -->
                                    <div class="display-none" data-element="beauty_pro_intro_div">
                                        <div class="row justify-content-center">
                                            <div class="colbl-md-10">
                                                <p class="text-center">In order to have access to this section you must confirm you hold a valid beauty license issued by the Board of Barbering and Cosmetology.</p>

                                                <p class="text-center">Click VERIFY LICENSE to confirm and join Beauty Pro community.<br>
                                                Click LOGIN if you already have a profile on Beautician List.</p>
                                            </div>
                                        </div>
                                        <div class="row justify-content-center" data-element="beauty_pro_signup_login_div">
                                            <div class="colbl-5">
                                                <button type="button" class="btn btn-primary btn-block" data-element="beauty_pro_signup_popup">SignUp</button>
                                            </div>
                                            <div class="colbl-5">
                                                <button type="button" class="btn btn-primary btn-block" data-element="beauty_pro_login_popup">Login</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Login step -->
                                    <div class="display-none" data-element="beauty_pro_login_div">
                                        <div class="blist__form__header">
                                            <h2>Login as Beauty Pro</h2>
                                        </div>
                                        <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                            <input type="hidden" name="action" value="beauty_pro_login">
                                            <input type="hidden" name="nonce" value="'.$nonce.'">
                                            <div class="form-group">
                                                <input class="form-control" placeholder="Email" autocomplete="new-password" type="text" name="email">
                                            </div>
                                            <div class="form-group">
                                                <input class="form-control" placeholder="Password" autocomplete="new-password" type="password" name="password">
                                            </div>
                                            <button type="button" class="blist__button blist__button--primary" data-element="beauty_pro_login">Login</button>
                                            <p class="beauty_pro_text_error" data-element="beauty_pro_login_error"></p>
                                        </form>
                                        <hr class="blist__hr blist__hr--dashed is-small">
                                        <div class="blist__dialog__links blist__dialog__links--horizontal">
                                            <ul>
                                                <li><a href="#" data-element="beauty_pro_login_forgot_popup">Forgot Password</a></li>
                                                <li><a href="#" data-element="beauty_pro_signup_popup">Create Account</a></li>
                                                <li><a href="#" data-element="beauty_pro_support_popup">Report a problem</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Forgot step -->
                                    <div class="display-none" data-element="beauty_pro_forgot_div">
                                        <div class="blist__form__header">
                                            <h2>Reset Password</h2>
                                        </div>
                                        <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                            <input type="hidden" name="action" value="beauty_pro_forgot">
                                            <input type="hidden" name="nonce" value="'.$nonceForgot.'">
                                            <div class="form-group">
                                                <input class="form-control" placeholder="Email" autocomplete="new-password" type="text" name="email">
                                            </div>
                                            <button type="button" class="blist__button blist__button--primary" data-element="beauty_pro_forgot">Send Recovery Email</button>
                                            <p class="beauty_pro_text_error" data-element="beauty_pro_forgot_error"></p>
                                        </form>
                                        <hr class="blist__hr blist__hr--dashed is-small">
                                        <div class="blist__dialog__links blist__dialog__links--horizontal">
                                            <ul>
                                                <li><a href="#" data-element="beauty_pro_login_popup">Sign In</a></li>
                                                <li><a href="#" data-element="beauty_pro_signup_popup">Create Account</a></li>
                                                <li><a href="#" data-element="beauty_pro_support_popup">Report a problem</a></li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Support step -->
                                    <div class="display-none" data-element="beauty_pro_support_popup_div">
                                        <div class="blist__form__header">
                                            <h2>Report a problem</h2>
                                        </div>
                                        <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                            <input type="hidden" name="action" value="beauty_pro_support">
                                            <input type="hidden" name="nonce" value="'.$nonceSupport.'">
                                            <div class="form-group">
                                                <input class="form-control" placeholder="Your name" autocomplete="off" type="text"
                                                    name="name">
                                            </div>
                                            <div class="form-group">
                                                <input class="form-control" placeholder="Your email" autocomplete="off" type="text"
                                                    name="email">
                                            </div>
                                            <div class="form-group">
                                                <textarea style="height: auto;" rows="4" cols="60" class="form-control" placeholder="Comments / Notes" autocomplete="off"
                                                    name="message"></textarea>
                                            </div>
                                            '.$supportCaptcha.'

                                            <button type="button" class="blist__button blist__button--primary" data-element="beauty_pro_support">Send message</button>
                                            <p class="beauty_pro_text_error" data-element="beauty_pro_support_error"></p>
                                        </form>
                                        <p data-element="beauty_pro_support_message" class="display-none">Thank you for your inquiry. We will review and get
                                            back to you shortly.</p>

                                        <hr class="blist__hr blist__hr--dashed is-small">
                                        <div class="blist__dialog__links blist__dialog__links--horizontal">
                                            <ul>
                                                <li><a href="#" data-element="beauty_pro_login_popup">Sign In</a></li>
                                                <li><a href="#" data-element="beauty_pro_signup_popup">Create Account</a></li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Internatioan customers step -->
                                    <div class="display-none" data-element="beauty_pro_international_popup_div">
                                        <div class="blist__form__header">
                                            <h2 class="text-center">International Customers</h2>
                                            <p>Thank you for your interest in joining our platform.</p>
                                            <p>Please fill out the information below, and someone will get back to you shortly.</p>
                                        </div>
                                        <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                            <input type="hidden" name="action" value="beauty_pro_international">
                                            <input type="hidden" name="nonce" value="'.$nonceInternational.'">
                                            <div class="form-group">
                                                <input class="form-control" placeholder="Your name" autocomplete="off" type="text"
                                                    name="name">
                                            </div>
                                            <div class="form-group">
                                                <input class="form-control" placeholder="Your email" autocomplete="off" type="text"
                                                    name="email">
                                            </div>
                                            <div class="form-group">
                                                <textarea style="height: auto;" rows="4" cols="60" class="form-control" placeholder="Comments / Notes" autocomplete="off"
                                                    name="message"></textarea>
                                            </div>
                                            '.$internationalCaptcha.'
                                            <button type="button" class="blist__button blist__button--primary" data-element="beauty_pro_international">Send message</button>
                                            <p class="beauty_pro_text_error" data-element="beauty_pro_international_error"></p>
                                        </form>
                                        <p data-element="beauty_pro_international_message" class="display-none">Thank you for your inquiry. We will review and get
                                            back to you shortly.</p>
                                        <hr class="blist__hr blist__hr--dashed is-small">
                                        <div class="blist__dialog__links blist__dialog__links--horizontal">
                                            <ul>
                                                <li><a href="#" data-element="beauty_pro_login_popup">Sign In</a></li>
                                                <li><a href="#" data-element="beauty_pro_signup_popup">Create Account</a></li>
                                            </ul>
                                        </div>
                                    </div>


                                    <!-- Signup step 1 -->
                                    <div data-element="beauty_pro_signup_popup_div">
                                        <div>
                                            <div class="blist__form__header">
                                                <p>Verify your license with Licensify and join our distinguished A-List community! Automatically create a free business profile on the BeautyAList professional beauty network. Join now and boost your career! <a target="blank" href="https://beautyalist.com/pages/beautyalist-how-it-works">Learn More</a></p>
                                                <p> Already have a profile on Beautician List?  <a href="#" data-element="beauty_pro_login_popup"><strong>LOGIN HERE</strong></a> </p>
                                            </div>

                                            <ul class="nav nav-tabs justify-content-center m-0" id="myTab" role="tablist">
                                                <li class="nav-item">
                                                    <a class="nav-link active w120" id="individual-tab" data-toggle="tab" href="#individual" role="tab" aria-controls="individual" aria-selected="true">Individual</a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link w120" id="salon-tab" data-toggle="tab" href="#salon" role="tab" aria-controls="salon" aria-selected="false">Salon</a>
                                                </li>
                                            </ul>
                                            <div class="tab-content" id="myTabContent">
                                                <div class="tab-pane fade show active" id="individual" role="tabpanel" aria-labelledby="individual-tab">
                                                    <br>
                                                    <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                                        <input type="hidden" name="action" value="beauty_pro_verification">
                                                        <input type="hidden" data-element="beauty_pro_verification_short_state" name="shortstate" value="">
                                                        <input type="hidden" name="nonce" value="'.$nonceVerification.'">
                                                        <input type="hidden" data-element="beauty_pro_verification_config" data-source="'.$source.'">
                                                        <div class="form-row">
                                                            <div class="col">
                                                                <div class="form-group">
                                                                    <input class="form-control" type="text" name="firstname" placeholder="First name">
                                                                    <small class="invalid-message display-none" data-element="beauty_pro_verification_error_firstname"></small>
                                                                </div>
                                                            </div>
                                                            <div class="col">
                                                                <div class="form-group">
                                                                    <input class="form-control" type="text" name="lastname" placeholder="Last name *"> 
                                                                    <small class="invalid-message display-none" data-element="beauty_pro_verification_error_lastname"></small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <input class="form-control" type="text" name="email" placeholder="Email *">
                                                            <small class="invalid-message display-none" data-element="beauty_pro_verification_error_email"></small>
                                                        </div>
                                                        '.$countryElement.'
                                                        '.$stateElement.'
                                                        <div class="form-group">
                                                            <p class="mb-0" data-element="beauty_pro_verification_license_hint"></p>
                                                            <input class="form-control" type="text" name="license" placeholder="License Number *">
                                                            <small class="invalid-message display-none" data-element="beauty_pro_verification_error_license"></small>
                                                        </div>
                                                        <div data-element="beauty_pro_verification_license_image" class="form-group display-none">
                                                            <small>Please upload a copy of your license in order to be verified.</small>
                                                            <div>
                                                                <input class="form-control height-auto"  type="file" id="image" />
                                                            </div>
                                                            '.$maxFileSize.'
                                                            <small class="invalid-message display-none" data-element="beauty_pro_verification_error_image"></small>
                                                        </div>
                                                        '.$registrationTechCaptcha.'
                                                        <button type="button" class="blist__button blist__button--primary" data-element="beauty_pro_verification">Continue Verification</button>
                                                        <p class="beauty_pro_text_error" data-element="beauty_pro_verification_error"></p>
                                                        <div class="blist__indentr"></div>
                                                        <div class="blist__font--hint blist__align--center">By continuing to the next page I certify that all of the above information is true.</div>
                                                    </form>
                                                </div>
                                                <div class="tab-pane fade" id="salon" role="tabpanel" aria-labelledby="salon-tab">
                                                    <br>
                                                    <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                                        <input type="hidden" name="action" value="beauty_pro_salon_verification">
                                                        <input type="hidden" data-element="beauty_pro_verification_short_state" name="shortstate" value="">
                                                        <input type="hidden" name="nonce" value="'.$nonceSalonVerification.'">
                                                        <input type="hidden" data-element="beauty_pro_verification_config" data-source="'.$source.'">
                                                        <div class="form-group">
                                                            <input class="form-control" type="text" name="businessname" placeholder="Business name *"> 
                                                            <small class="invalid-message display-none" data-element="beauty_pro_salon_verification_error_businessname"></small>
                                                        </div>
                                                        <div class="form-group">
                                                            <input class="form-control" type="text" name="email" placeholder="Email *">
                                                            <small class="invalid-message display-none" data-element="beauty_pro_salon_verification_error_email"></small>
                                                        </div>
                                                        '.$countryElement.'
                                                        '.$stateElement.'
                                                        <div class="form-group">
                                                            <p class="mb-0" data-element="beauty_pro_salon_verification_license_hint"></p>
                                                            <input class="form-control" type="text" name="license" placeholder="License Number *">
                                                            <small class="invalid-message display-none" data-element="beauty_pro_salon_verification_error_license"></small>
                                                        </div>
                                                        <div data-element="beauty_pro_salon_verification_license_image" class="form-group display-none">
                                                            <small>Please upload a copy of your license in order to be verified.</small>
                                                            <div>
                                                                <input class="form-control height-auto"  type="file" id="image" />
                                                            </div>
                                                            '.$maxFileSize.'
                                                            <small class="invalid-message display-none" data-element="beauty_pro_salon_verification_error_image"></small>
                                                        </div>
                                                        '.$registrationSalonCaptcha.'

                                                        <button type="button" class="blist__button blist__button--primary" data-element="beauty_pro_salon_verification">Continue verification</button>
                                                        <p class="beauty_pro_text_error" data-element="beauty_pro_salon_verification_error"></p>
                                                        <div class="blist__indentr"></div>
                                                        <div class="blist__font--hint blist__align--center">By continuing to the next page I certify that all of the above information is true.</div>
                                                    </form>
                                                </div>
                                            </div>

                                            <hr class="blist__hr blist__hr--dashed is-small">
     
                                            <div class="blist__dialog__links blist__dialog__links--vertical">
                                                <ul>
                                                    <li>International customer?  <a href="#" data-element="beauty_pro_international_popup"><strong>click here</strong></a></li>
                                                    <li><a href="#" data-element="beauty_pro_support_popup">Report a problem</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Signup step 1.1 -->
                                    <div class="display-none" data-element="beauty_pro_signup_step_1_1_popup_div">
                                        <div>
                                            <div class="blist__form__header" data-element="beauty_pro_signup_step_1_1_hint"></div>
                                            <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                                <input type="hidden" name="action" value="beauty_pro_verification_step2">
                                                <input type="hidden" name="nonce" value="'.$nonceVerificationStep2.'">
                                                <input type="hidden" name="image">
                                                <input type="hidden" name="country">
                                                <input type="hidden" name="state">
                                                <input type="hidden" name="firstname">
                                                <input type="hidden" name="lastname">
                                                <input type="hidden" name="email">
                                                <input type="hidden" name="expired">
                                                <input type="hidden" name="type">
                                                <input type="hidden" name="number">

                                                <div class="Bl-LicenseList"></div>
                                                
                                                <small class="invalid-message display-none" data-element="beauty_pro_signup_error_beauty_pro_signup_step1_radio"></small>
                                                <div class="form-group">
                                                     <!--
                                                        <div class="form-control speciality_btn" data-element="beauty_pro_signup_error_speciality_popup">
                                                            <div class="speciality_btn_label" data-element="beauty_pro_signup_error_speciality_items"><span>Speciality:</span> None</div>
                                                            <div class="speciality_btn_icon"></div>
                                                        </div>
                                                        <small class="invalid-message display-none" data-element="beauty_pro_signup_error_speciality[]"></small>
                                                        <div class="speciality-filter__list display-none" data-element="beauty_pro_signup_error_speciality_div">'.$specialitiesStr.'</div>
                                                    -->

                                                    <dl class="blist__field blist__field--vertical">
                                                        <dt>
                                                            <label for="registration[speciality]">Choose your Speciality <span class="required">*</span></label>
                                                        </dt>
                                                        <dd data-element="beauty_pro_signup_error_speciality_items">
                                                            <select multiple="" class="blist__input blist__input--select is-required" data-element="beauty_pro_signup_error_speciality_div" id="registration[speciality]" name="speciality[]" data-gtm-form-interact-field-id="5">
                                                                '.$specialitiesStr.'
                                                            </select>
                                                            <div class="blist__field__hint is-secondary">Click CTRL to select multiple options</div><br>
                                                            <small class="invalid-message display-none" data-element="beauty_pro_signup_error_speciality[]"></small>
                                                        </dd>
                                                    </dl>
                                                </div>
                                                <div class="form-group">
                                                    <div class="blist__check">
                                                        <label class="blist__check__label" for="confirm">
                                                            <input class="blist__input blist__input--check" type="checkbox" name="confirm" id="confirm">
                                                            <span class="blist__check__text">By ticking this box I agree that I have read the <a href="https://beauticianlist.com/pages/disclaimer-licensedbeautician" target="_blank">disclaimer</a>. I consent to receive further electronic communication from BeauticianList and be onboarded to its platform.</span>
                                                        </label>
                                                    </div>
                                                    <small class="invalid-message display-none" data-element="beauty_pro_signup_error_confirm"></small>
                                                </div>
                                                
                                                <div class="row justify-content-center">
                                                    <div class="colbl-12">
                                                        <button type="button" class="blist__button blist__button--primary" data-element="beauty_pro_verification_try">Try Again</button>
                                                    </div>
                                                    <div class="colbl-12">
                                                        <button type="button" class="blist__button blist__button--primary" data-element="beauty_pro_verification_support">Contact Us</button>
                                                    </div>
                                                    <div class="colbl-12">
                                                        <button type="button" class="blist__button blist__button--primary" data-element="beauty_pro_signup">Sign Up</button>
                                                    </div>
                                                </div>
                                                <p class="beauty_pro_text_error" data-element="beauty_pro_verification_error"></p>
                                            </form>

                                            <hr class="blist__hr blist__hr--dashed is-small">
     
                                            <div class="blist__dialog__links blist__dialog__links--vertical">
                                                <ul>
                                                    <li>International customer?  <a href="#" data-element="beauty_pro_international_popup"><strong>click here</strong></a></li>
                                                    <li><a href="#" data-element="beauty_pro_support_popup">Report a problem</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Signup step 2 -->
                                    <div class="display-none" data-element="beauty_pro_signup_step2_popup_div">
                                        <div>
                                            <div class="mb-3 text-center">
                                                <p>Thank you for submitting your information.</p>
                                                <div data-element="beauty_pro_signup_step2_hint"></div>
                                            </div>
                                            <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                                <input type="hidden" name="action" value="beauty_pro_verification_step2">
                                                <input type="hidden" name="nonce" value="'.$nonceVerificationStep2.'">
                                                <input type="hidden" name="image">
                                                <input type="hidden" name="state">
                                                <input type="hidden" name="latitude">
                                                <input type="hidden" name="longitude">
                                                <input type="hidden" name="firstname">
                                                <input type="hidden" name="lastname">
                                                <input type="hidden" name="email">
                                                <input type="hidden" name="expired">
                                                <input type="hidden" name="type">
                                                <input type="hidden" name="number">
                                                <div class="form-group">
                                                    <input class="form-control" type="text" name="firstname-label" disabled>
                                                </div>
                                                <div class="form-group">
                                                    <input class="form-control" type="text" name="lastname-label" disabled>
                                                </div>
                                                <div class="form-group">
                                                    <input class="form-control" type="text" name="email-label" disabled>
                                                </div>
                                                <div class="form-group">
                                                    <input class="form-control" type="text" name="full-state" disabled>
                                                </div>
                                                <div class="form-group">
                                                    <input class="form-control" type="text" name="number-label" disabled>
                                                </div>
                                                <div class="form-group">
                                                    <input class="form-control" type="text" name="nickname" placeholder="Nickname *">
                                                    <small class="invalid-message display-none" data-element="beauty_pro_signup_error_nickname"></small>
                                                </div>
                                                <div class="form-group">
                                                    <input class="form-control" type="text" name="mobile" placeholder="Mobile">
                                                </div>
                                                <div class="form-group">
                                                    <input class="form-control" type="password" name="password" placeholder="Password *">
                                                    <small class="invalid-message display-none" data-element="beauty_pro_signup_error_password"></small>
                                                </div>
                                                <div class="form-group">
                                                    <input class="form-control" type="password" name="repeat-password" placeholder="Repeat password *">
                                                    <small class="invalid-message display-none" data-element="beauty_pro_signup_error_repeat-password"></small>
                                                </div>
                                                <div class="form-group">
                                                    <input class="form-control" type="text" name="address" id="beauty_pro_signup_autocomplete" placeholder="Working address">
                                                    <small> - if you do not enter your working address clients will not be able to find you via location feature.</small>
                                                </div>
                                                <div class="form-group">
                                                    <!--<input data-element="beauty_pro_signup_error_speciality_popup" class="form-control" type="text" name="speciality" placeholder="Speciality *">-->
                                                    <div class="form-control speciality_btn" data-element="beauty_pro_signup_error_speciality_popup">
                                                        <div class="speciality_btn_label" data-element="beauty_pro_signup_error_speciality_items"><span>Speciality:</span> None</div>
                                                        <div class="speciality_btn_icon"></div>
                                                    </div>
                                                    <small class="invalid-message display-none" data-element="beauty_pro_signup_error_speciality[]"></small>
                                                    <div class="speciality-filter__list display-none" data-element="beauty_pro_signup_error_speciality_div">'.$specialitiesStr.'</div>
                                                </div>
                                                <div class="form-group">
                                                    <div class="blist__check">
                                                        <label class="blist__check__label" for="uploadDocuments">
                                                            <input class="blist__input blist__input--check" data-element="beauty_pro_upload_documents" type="checkbox" name="uploadDocuments" id="uploadDocuments">                                         
                                                            I want to upload my tax documents.
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="form-group display-none" data-target="beauty_pro_upload_documents_div">
                                                    <label class="blist__check__label" for="certFile">
                                                        <input class="btn btn-block" type="file" name="certFile" id="certFile">  
                                                        Resale certificate
                                                    </label>
                                                </div>
                                                <div class="form-group display-none" data-target="beauty_pro_upload_documents_div">
                                                    <label class="blist__check__label" for="formFile">
                                                        <input class="btn btn-block" type="file" name="formFile" id="formFile">
                                                        W9 tax form
                                                    </label>
                                                </div>
                                                <div class="form-group">
                                                    <div class="blist__check">
                                                        <label class="blist__check__label" for="subscribe">
                                                            <input class="blist__input blist__input--check" type="checkbox" checked="checked" name="subscribe" id="subscribe">                                          
                                                            Subscribe to email updates
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <div class="blist__check">
                                                        <label class="blist__check__label" for="subscribeAdditional">
                                                            <input class="blist__input blist__input--check" type="checkbox" checked="checked" name="subscribeAdditional" id="subscribeAdditional">                                          
                                                            Opt in to our third party list to receive relevant offers from our sponsors and partners
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <div class="blist__check">
                                                        <label class="blist__check__label" for="confirm">
                                                            <input class="blist__input blist__input--check" type="checkbox" name="confirm" id="confirm">
                                                            I have read and understand the <a href="https://beauticianlist.com/pages/disclaimer-licensedbeautician" target="_blank">disclaimer</a> *
                                                        </label>
                                                    </div>
                                                    <small class="invalid-message display-none" data-element="beauty_pro_signup_error_confirm"></small>
                                                </div>
                                                <button type="button" class="btn btn-primary btn-block" data-element="beauty_pro_signup">Sign Up</button>
                                                <p class="beauty_pro_text_error" data-element="beauty_pro_signup_error"></p>
                                            </form>
                                            <hr>
                                            <div class="row justify-content-center">
                                                <div class="col text-center">
                                                    <a href="#" data-element="beauty_pro_login_popup">Already Have Account? Sign IN!</a>
                                                </div>
                                            </div>
                                            <div class="Bl-link-report">
                                                <a href="#" data-element="beauty_pro_support_popup">Report a problem</a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Update step 1 -->
                                    <div class="display-none" data-element="beauty_pro_update_popup_div">
                                        <div>
                                            <div class="blist__form__header"></div>
                                            <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                                <input type="hidden" name="action" value="beauty_pro_update">
                                                <input type="hidden" data-element="beauty_pro_update_short_state" name="shortstate" value="">
                                                <input type="hidden" name="nonce" value="'.$nonceUpdate.'">
                                                <input type="hidden" data-element="beauty_pro_update_config" data-source="'.$source.'">
                                                <input type="hidden" name="role" value="">
                                                <div class="form-group">
                                                    <input class="form-control" type="text" name="firstname" placeholder="First name *">
                                                    <small class="invalid-message display-none" data-element="beauty_pro_update_error_firstname"></small>
                                                </div>
                                                <div class="form-group">
                                                    <input class="form-control" type="text" name="lastname" placeholder="Last name *"> 
                                                    <small class="invalid-message display-none" data-element="beauty_pro_update_error_lastname"></small>
                                                </div>
                                                <div class="form-group">
                                                    <input class="form-control" type="text" name="email" placeholder="Email *">
                                                    <small class="invalid-message display-none" data-element="beauty_pro_update_error_email"></small>
                                                </div>
                                                <div class="form-group">
                                                    <input class="form-control" name="state" data-element="beauty_pro_update_state" data-source="'.$states.'" placeholder="State *">
                                                    <small class="invalid-message display-none" data-element="beauty_pro_update_error_state"></small>
                                                </div>
                                                <div class="form-group">
                                                    <p class="mb-0" data-element="beauty_pro_update_license_hint"></p>
                                                    <input class="form-control" type="text" name="license" placeholder="License Number *">
                                                    <small class="invalid-message display-none" data-element="beauty_pro_update_error_license"></small>
                                                </div>
                                                <div data-element="beauty_pro_update_license_image" class="form-group display-none">
                                                    <small>Please upload a copy of your license in order to be verified.</small>
                                                    <div>
                                                        <input class="form-control height-auto"  type="file" id="image" />
                                                    </div>
                                                    '.$maxFileSize.'
                                                    <small class="invalid-message display-none" data-element="beauty_pro_update_error_image"></small>
                                                </div>
                                                <button type="button" class="blist__button blist__button--primary" data-element="beauty_pro_update_verification">Continue verification</button>
                                                <p class="beauty_pro_text_error" data-element="beauty_pro_update_verfication_error"></p>
                                            </form>
                                            <hr class="blist__hr blist__hr--dashed is-small">
                                            <div class="blist__dialog__links blist__dialog__links--horizontal">
                                                <ul>
                                                    <li><a href="#" data-element="beauty_pro_login_forgot_popup">Forgot Password</a></li>
                                                    <li><a href="#" data-element="beauty_pro_signup_popup">Create Account</a></li>
                                                    <li><a href="#" data-element="beauty_pro_support_popup">Report a problem</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Update step 1.1 -->
                                    <div class="display-none" data-element="beauty_pro_update_step_1_1_popup_div">
                                        <div>
                                            <h4 class="mb-4 font-weight-normal text-center">Please choose the correct option:</h4>
                                            <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                                <div class="Bl-LicenseList"></div>
                                                <p class="mb-3 font-italic text-center"><small>By continuing to the next page I certify that all of the above information is true.</small></p>
                                                <div class="row justify-content-center">
                                                    <div class="colbl-5">
                                                        <button type="button" class="btn btn-primary btn-block" data-element="beauty_pro_update_try">Try Again</button>
                                                    </div>
                                                    <div class="colbl-5 display-none">
                                                        <button type="button" class="btn btn-primary btn-block" data-element="beauty_pro_update_continue">Update License</button>
                                                    </div>
                                                </div>
                                                <p class="beauty_pro_text_error" data-element="beauty_pro_update_verfication_error"></p>
                                            </form>
                                            <hr>
                                            <div class="row justify-content-center">
                                                <div class="col text-center">
                                                    <a href="#" data-element="beauty_pro_login_popup">Already Have Account? Sign IN!</a>
                                                </div>
                                            </div>
                                            <div class="Bl-link-report">
                                                <a href="#" data-element="beauty_pro_support_popup">Report a problem</a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Update step 2 -->
                                    <div class="display-none" data-element="beauty_pro_update_step2_popup_div">
                                        <div>
                                            <div class="mb-3 text-center">
                                                <p>Thank you for submitting your information!</p>
                                                <div data-element="beauty_pro_update_step2_hint"></div>
                                            </div>
                                            <form action="'.admin_url('admin-ajax.php').'" method="POST">
                                                <input type="hidden" name="action" value="beauty_pro_update_step2">
                                                <input type="hidden" name="nonce" value="'.$nonceUpdateStep2.'">
                                                <input type="hidden" name="image">
                                                <input type="hidden" name="state">
                                                <input type="hidden" name="firstname">
                                                <input type="hidden" name="lastname">
                                                <input type="hidden" name="id">
                                                <input type="hidden" name="number">
                                                <input type="hidden" name="expired">
                                                <input type="hidden" name="type">
                                            </form>
                                            <hr>
                                            <div class="row justify-content-center">
                                                <div class="col text-center">
                                                    <a href="#" data-element="beauty_pro_login_popup">Already Have Account? Sign IN!</a>
                                                </div>
                                            </div>
                                            <div class="Bl-link-report">
                                                <a href="#" data-element="beauty_pro_support_popup">Report a problem</a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Signup step 2 notification -->
                                    <div class="display-none" data-element="beauty_pro_update_step2_notify_popup_div">
                                        <div>
                                            <div class="mb-3 text-center">
                                                <p>Thank you for signing up!</p>
                                                <div>
                                                    <p>Thank you for providing us with your professional credentials.</p>
                                                    <p>Your profile is now set up but with limited capabilities.</p>
                                                    <p>Please note that your profile is not complete until we verify the validity of your license.</p>
                                                </div>
                                            </div>
                                            <hr>
                                            <div class="row justify-content-center">
                                                <div class="col text-center">
                                                    <a href="#" data-element="beauty_pro_login_popup">Already Have Account? Sign IN!</a>
                                                </div>
                                                <div class="col text-center">
                                                    <a href="#" data-element="beauty_pro_signup_popup">Create Account</a>
                                                </div>
                                            </div>
                                            <div class="Bl-link-report">
                                                <a href="#" data-element="beauty_pro_support_popup">Report a problem</a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Messages notification -->
                                    <div class="display-none" data-element="beauty_pro_notify_popup_div">
                                        <div>
                                            <div class="mb-3 text-center">
                                                <div data-element="beauty_pro_notify_popup_message"></div>
                                            </div>
                                            <hr>
                                            <div class="row justify-content-center">
                                                <div class="col text-center">
                                                    <a href="#" data-element="beauty_pro_login_popup">Already Have Account? Sign IN!</a>
                                                </div>
                                                <div class="col text-center">
                                                    <a href="#" data-element="beauty_pro_signup_popup">Create Account</a>
                                                </div>
                                            </div>
                                            <div class="Bl-link-report">
                                                <a href="#" data-element="beauty_pro_support_popup">Report a problem</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        ';

        $enable = false;
        if (is_page() || is_single()) {
            if (class_exists('WooCommerce')) {
                if (!is_checkout() && !is_cart()) {
                    $enable = true;
                }
            } else {
                $enable = true;
            }
        }

        if (!$enable) {
            if (in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php'])) {
                $enable = true;
            }
        }

        if ($enable) {
            echo $str;
        }
    }

    public function remove_add_to_cart_buttons()
    {
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 1);
    }

    public function handle_form_login_submit()
    {
        $res = [];
        if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-login')) {
            $api = new BeautyLicenseVerificationAPI();
            $res = $api->login(sanitize_email($_POST['email']), sanitize_text_field($_POST['password']));
        }

        echo json_encode($res);
        exit();
    }

    public function handle_form_forgot_submit()
    {
        $res       = [];
        $api       = new BeautyLicenseVerificationAPI();
        $resDetail = $api->getKeyDetail(get_option($this->option_name));
        if (!$resDetail['success']) {
            $res = ['success' => false, 'errors' => ['message' => 'Invalid / empty api key']];
        } else {
            if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-forgot')) {
                $res = $api->forgot(sanitize_email($_POST['email']));
            }
        }

        echo json_encode($res);
        exit();
    }

    public function handle_form_support_submit()
    {
        if (!$this->service->verifyCaptcha($_POST)) {
            wp_send_json(['success' => false, 'errors' => ['message' => 'Please provide valid captcha.']]);
        }

        $res       = [];
        $api       = new BeautyLicenseVerificationAPI();
        $resDetail = $api->getKeyDetail(get_option($this->option_name));
        if (!$resDetail['success']) {
            $res = ['success' => false, 'errors' => ['message' => 'Invalid / empty api key']];
        } else {
            if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-support')) {
                $data = [
                    'name'    => sanitize_text_field($_POST['name']),
                    'email'   => sanitize_email($_POST['email']),
                    'message' => sanitize_textarea_field($_POST['message']),
                    'source'  => $_SERVER['HTTP_HOST'],
                ];
                $res = $api->support($data);
            }
        }

        echo json_encode($res);
        exit();
    }

    public function handle_form_international_submit()
    {
        if (!$this->service->verifyCaptcha($_POST)) {
            wp_send_json(['success' => false, 'errors' => ['message' => 'Please provide valid captcha.']]);
        }

        $res = [];
        if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-international')) {
            $api  = new BeautyLicenseVerificationAPI();
            $data = [
                'name'    => sanitize_text_field($_POST['name']),
                'email'   => sanitize_email($_POST['email']),
                'message' => sanitize_textarea_field($_POST['message']),
                'source'  => $_SERVER['HTTP_HOST'],
            ];
            $res = $api->support($data);
        }

        echo json_encode($res);
        exit();
    }

    public function handle_form_logout_submit()
    {
        $res = [];
        if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-logout')) {
            $api = new BeautyLicenseVerificationAPI();
            $res = $api->logout();
        }

        echo json_encode($res);
        exit();
    }

    public function handle_form_update_submit()
    {
        $res = [];
        if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-update')) {
            $api = new BeautyLicenseVerificationAPI();
            $key = get_option($this->option_name);

            $data = [
                'apikey'     => $key,
                'licenceno'  => sanitize_text_field($_POST['license']),
                'lastname'   => sanitize_text_field($_POST['lastname']),
                'email'      => sanitize_text_field($_POST['email']),
                'source'     => $_SERVER['HTTP_HOST'],
                'state'      => sanitize_text_field($_POST['shortstate']),
            ];

            if ('salon' !== sanitize_text_field($_POST['role'])) {
                $data['firstname'] = sanitize_text_field($_POST['firstname']);
            } else {
                $data['businessname'] = sanitize_text_field($_POST['lastname']);
                unset($data['lastname']);
            }

            if ($_FILES && !empty($_FILES['image'])) {
                $data['image'] = $_FILES['image'];
            }

            $res = $api->updateFirstStep($data);
        }

        echo json_encode($res);
        exit();
    }

    public function handle_form_verification_submit()
    {
        if (!$this->service->verifyCaptcha($_POST)) {
            wp_send_json(['success' => false, 'errors' => ['message' => 'Please provide valid captcha.', 'code' => 1010]]);
        }

        $res = [];
        if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-verification')) {
            $api = new BeautyLicenseVerificationAPI();
            $key = get_option($this->option_name);

            $data = [
                'apikey'    => $key,
                'licenceno' => sanitize_text_field($_POST['license']),
                'lastname'  => sanitize_text_field($_POST['lastname']),
                'email'     => sanitize_text_field($_POST['email']),
                'source'    => $_SERVER['HTTP_HOST'],
                'country'   => sanitize_text_field($_POST['country']),
                'state'     => sanitize_text_field($_POST['shortstate']),
            ];

            if (array_key_exists('firstname', $_POST)) {
                $data['firstname'] = sanitize_text_field($_POST['firstname']);
            }

            if ($_FILES && !empty($_FILES['image'])) {
                $data['image'] = $_FILES['image'];
            }

            $res = $api->signupFirstStep($data);
            if ($res && true == $res['success']) {
                if (array_key_exists('email', $res['data'])) {
                    $newData = [
                        'email'    => $res['data']['email'],
                        'lastname' => $res['data']['lastname'],
                        'country'  => $res['data']['country'],
                        'state'    => $res['data']['address']['state'],
                        'number'   => $res['data']['license']['number'],
                    ];

                    if (array_key_exists('firstname', $res['data'])) {
                        $newData['firstname'] = $res['data']['firstname'];
                    }

                    if (array_key_exists('image', $res['data'])) {
                        $newData['image'] = $res['data']['image'];
                    }

                    $res['data'] = [$newData];
                }
            }
        }

        echo json_encode($res);
        exit();
    }

    public function handle_form_verification_support_submit()
    {
        $messages = [];
        $res      = [];
        if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-verification')) {
            $api = new BeautyLicenseVerificationAPI();
            $key = get_option($this->option_name);

            $data = [
                'apikey'    => $key,
                'licenceno' => sanitize_text_field($_POST['license']),
                'lastname'  => sanitize_text_field($_POST['lastname']),
                'email'     => sanitize_text_field($_POST['email']),
                'source'    => $_SERVER['HTTP_HOST'],
                'country'   => sanitize_text_field($_POST['country']),
                'state'     => sanitize_text_field($_POST['shortstate']),
            ];

            if (array_key_exists('firstname', $_POST)) {
                $data['firstname'] = sanitize_text_field($_POST['firstname']);
                $messages[]        = 'First Name: '.$data['firstname'];
            }
            $messages[] = 'Last Name: '.$data['lastname'];
            $messages[] = 'State: '.$data['state'];
            $messages[] = 'Country: '.$data['country'];
            $messages[] = 'License Number: '.$data['licenceno'];
            $messages[] = 'Email: '.$data['email'];

            $api  = new BeautyLicenseVerificationAPI();
            $data = [
                'name'    => 'License not found - Timeout',
                'email'   => $data['email'],
                'message' => implode("\r\n", $messages),
                'source'  => $_SERVER['HTTP_HOST'],
            ];
            $res = $api->support($data);
        }

        echo json_encode($res);
        exit();
    }

    public function handle_form_salon_verification_submit()
    {
        if (!$this->service->verifyCaptcha($_POST)) {
            wp_send_json(['success' => false, 'errors' => ['message' => 'Please provide valid captcha.', 'code' => 1010]]);
        }

        $res = [];
        if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-salon-verification')) {
            $api = new BeautyLicenseVerificationAPI();
            $key = get_option($this->option_name);

            $data = [
                'apikey'        => $key,
                'licenceno'     => sanitize_text_field($_POST['license']),
                'businessname'  => sanitize_text_field($_POST['businessname']),
                'email'         => sanitize_text_field($_POST['email']),
                'source'        => $_SERVER['HTTP_HOST'],
                'country'       => sanitize_text_field($_POST['country']),
                'state'         => sanitize_text_field($_POST['shortstate']),
            ];

            if ($_FILES && !empty($_FILES['image'])) {
                $data['image'] = $_FILES['image'];
            }

            $res = $api->signupSalonFirstStep($data);
            if ($res && true == $res['success']) {
                if (array_key_exists('email', $res['data'])) {
                    $newData = [
                        'email'    => $res['data']['email'],
                        'lastname' => $res['data']['lastname'],
                        'state'    => $res['data']['address']['state'],
                        'number'   => $res['data']['license']['number'],
                    ];

                    if (array_key_exists('firstname', $res['data'])) {
                        $newData['firstname'] = $res['data']['firstname'];
                    }

                    if (array_key_exists('image', $res['data'])) {
                        $newData['image'] = $res['data']['image'];
                    }

                    $res['data'] = [$newData];
                }
            }
        }

        echo json_encode($res);
        exit();
    }

    public function handle_form_salon_verification_support_submit()
    {
        $messages = [];
        $res      = [];
        if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-salon-verification')) {
            $api = new BeautyLicenseVerificationAPI();
            $key = get_option($this->option_name);

            $data = [
                'apikey'        => $key,
                'licenceno'     => sanitize_text_field($_POST['license']),
                'businessname'  => sanitize_text_field($_POST['businessname']),
                'email'         => sanitize_text_field($_POST['email']),
                'source'        => $_SERVER['HTTP_HOST'],
                'country'       => sanitize_text_field($_POST['country']),
                'state'         => sanitize_text_field($_POST['shortstate']),
            ];

            $messages[] = 'Businessname: '.$data['businessname'];
            $messages[] = 'State: '.$data['state'];
            $messages[] = 'Country: '.$data['country'];
            $messages[] = 'License Number: '.$data['licenceno'];
            $messages[] = 'Email: '.$data['email'];

            $api  = new BeautyLicenseVerificationAPI();
            $data = [
                'name'    => 'License not found - Timeout',
                'email'   => $data['email'],
                'message' => implode("\r\n", $messages),
                'source'  => $_SERVER['HTTP_HOST'],
            ];
            $res = $api->support($data);
        }

        echo json_encode($res);
        exit();
    }

    public function handle_form_verification_step2_submit()
    {
        $res = [];
        if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-verification-step2')) {
            $api = new BeautyLicenseVerificationAPI();

            $key   = get_option($this->option_name);
            $files = [];
            $data  = [
                'apikey'    => $key,
                'email'     => sanitize_email($_POST['email']),
                'source'    => sanitize_text_field($_SERVER['HTTP_HOST']),
                // 'shop'      => sanitize_text_field($_SERVER['HTTP_HOST']),
                'lastname'  => sanitize_text_field($_POST['lastname']),

                'country'             => sanitize_text_field($_POST['country']),
                'state'               => sanitize_text_field($_POST['state']),
                'number'              => sanitize_text_field($_POST['number']),
                'subscribe'           => 'on',
                'subscribeAdditional' => 'on',
            ];

            if (array_key_exists('firstname', $_POST)) {
                $data['firstname'] = sanitize_text_field($_POST['firstname']);
            }

            $items = ['image', 'expired', 'type'];
            foreach ($items as $value) {
                if (array_key_exists($value, $_POST) && !empty(sanitize_text_field($_POST[$value]))) {
                    $data[$value] = sanitize_text_field($_POST[$value]);
                }
            }

            if (array_key_exists('speciality', $_POST)) {
                $arr = [];
                foreach ($_POST['speciality'] as $speciality) {
                    $arr[] = sanitize_text_field($speciality);
                }

                if ($arr) {
                    $data['speciality'] = $arr;
                }
            }

            if (!empty($_POST['firstname'])) {
                $res = $api->signupSecondStep($data);
            } else {
                $res = $api->signupSalonSecondStep($data);
            }
        }

        echo json_encode($res);
        exit();
    }

    public function handle_form_update_step2_submit()
    {
        $res = [];
        if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-update-step2')) {
            $api = new BeautyLicenseVerificationAPI();

            $key = get_option($this->option_name);

            $data = [
                'apikey'    => $key,
                'id'        => sanitize_text_field($_POST['id']),
                'licenseno' => sanitize_text_field($_POST['number']),
                'state'     => sanitize_text_field($_POST['state']),
                'expired'   => sanitize_text_field($_POST['expired']),
                'type'      => sanitize_text_field($_POST['type']),
            ];
            if (array_key_exists('image', $_POST)) {
                $data['image'] = sanitize_text_field($_POST['image']);
            }

            $res = $api->updateSecondStep($data);
        }

        echo json_encode($res);
        exit();
    }

    public function checkout_create_order($orderId, $postedData, $order)
    {
        $api = new BeautyLicenseVerificationAPI();
        $api->getKeyDetail(get_option($this->option_name));

        $res = false;
        if ($v = get_user_meta($order->get_customer_id(), 'bl_tag', true)) {
            if (get_option($this->tag_name)) {
                if (false !== strpos($v, get_option($this->tag_name))) {
                    $res = true;
                }
            }

            if (get_option($this->params_name)) {
                $params = [];
                try {
                    $params = json_decode(get_option($this->params_name), true);
                } catch (Exception $e) {
                }

                if (!empty($params['order_tags'])) {
                    $userTags = [];
                    $v        = explode(',', $v);
                    foreach ($v as $value) {
                        $userTags[] = trim($value);
                    }

                    foreach ($params['order_tags'] as $orderTag) {
                        if (!in_array($orderTag, $userTags)) {
                            $res = false;

                            continue;
                        }
                        $res = true;
                    }
                }
            }
        }

        if ($res) {
            $api->createOrder($orderId, $postedData, $order);
        }
    }

    public function handle_form_tos_submit()
    {
        $res = ['success' => false];
        if (array_key_exists('nonce', $_POST) && wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'beauty-pro-tos')) {
            $data = [];
            if (!empty($_POST['subscribe']) && 'on' == $_POST['subscribe']) {
                $data['main'] = true;
            }

            if (!empty($_POST['subscribeAdditional']) && 'on' == $_POST['subscribeAdditional']) {
                $data['additional'] = true;
            }

            $api = new BeautyLicenseVerificationAPI();

            $res1 = $api->updateTOS();
            if ($data) {
                $res2 = $api->updateSubscribe($data);
                if ($res1) {
                    $res['success'] = true;
                }
            } else {
                if ($res1) {
                    $res['success'] = true;
                }
            }
        }

        echo json_encode($res);
        exit();
    }

    public function user_register($user_id)
    {
        $api = new BeautyLicenseVerificationAPI();
        $api->getKeyDetail(get_option($this->option_name));

        if ($api->checkSession()) {
            if (get_option($this->tag_name)) {
                update_user_meta($user_id, 'bl_tag', get_option($this->tag_name));
            }

            if (get_option($this->params_name)) {
                $params = [];
                try {
                    $params = json_decode(get_option($this->params_name), true);
                } catch (Exception $e) {
                }

                if (!empty($params['register_tags'])) {
                    update_user_meta($user_id, 'bl_tag', implode(', ', $params['register_tags']));
                }
            }
        }
    }

    public function shortcode_button($atts = [])
    {
        $api     = new BeautyLicenseVerificationAPI();
        $api->getKeyDetail(get_option($this->option_name));

        return $this->service->getShortCodeButtton($api->getProfile(), get_option($this->params_name), $atts);
    }

    public function be_show_extra_profile_fields($user)
    {
        if (current_user_can('administrator')) {
            $value = esc_attr(get_the_author_meta('bl_tag', $user->ID));
            echo $this->service->getprofileField($value);
        }
    }

    public function be_save_extra_profile_fields($user_id)
    {
        if (current_user_can('administrator') && current_user_can('edit_user', $user_id)) {
            update_usermeta($user_id, 'bl_tag', esc_attr($_POST['bl_tag']));
        }
    }

    public function registration_form()
    {
        $str       = '';

        $api     = new BeautyLicenseVerificationAPI();
        $res     = $api->getKeyDetail(get_option($this->option_name));
        if ($res['success']) {
            $profile = $api->getProfile();

            if ($profile) {
                $fname = $profile['data']['profile']['firstname'];
                $lname = $profile['data']['profile']['lastname'];

                $nonce = wp_create_nonce('beauty-pro-logout');
                $url   = admin_url('admin-ajax.php');

                $logout = '<a data-nonce="'.$nonce.'" data-href="'.$url.'" class="button button-large" data-element="beauty_pro_logout" href="#">Logout</a>';

                $str = '
                    <p>
                        <span data-node="label">Logged in as '.$fname.' '.$lname.'. '.$logout.'</span>
                    </p>
                ';
            } else {
                $str = '
                    <p>
                        <button type="button" data-toggle="modal" class="button button-large" ip="blist_button" data-target="#beauty_pro_signup_login_popup">
                            <span data-node="label">Beauty Pro</span>
                        </button>
                    </p>
                ';
            }
        }

        echo $str;
    }

    public function inject_footer()
    {
        get_template_part('footer');
    }
}

// Instantiate a singleton of this plugin
$ba = new BLVSignupLogin();
