<?php

require_once 'db.php';

/**
 * Class BeautyLicenseVerification.
 */
class BeautyLicenseVerification
{
    private $option_name          = 'beauticianlist_apikey';
    private $option_google_name   = 'beauticianlist_google_key';
    private $option_google_secret = 'beauticianlist_google_secret';
    private $tag_name             = 'beauticianlist_tag';

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Add extra menu items for admins
        add_action('admin_menu', [$this, 'admin_menu']);

        // Added js and css
        add_action('admin_enqueue_scripts', [$this, 'added_scripts']);

        // Create end-points
        add_filter('query_vars', [$this, 'query_vars']);
        add_action('parse_request', [$this, 'parse_request']);

        // Use your hidden "action" field value when adding the remove tag action
        add_action('admin_post_beauty_pro_remove_tag', [$this, 'handle_beauty_pro_remove_tag']);
        add_action('admin_post_beauty_pro_add_tag', [$this, 'handle_beauty_pro_add_tag']);

        add_action('admin_post_beauty_pro_save_key', [$this, 'handle_beauty_pro_save_key']);

        add_action('admin_post_beauty_pro_save_google_key', [$this, 'handle_beauty_pro_save_google_key']);
    }

    /**
     * Add extra menu items for admins.
     */
    public function admin_menu()
    {
        $title = 'Licensify';
        add_menu_page(
            $title,
            $title,
            'manage_options',
            'blv_settings',
            [$this, 'blv_settings_func']
        );

        $newvalue    = '';
        if (get_option($this->option_name)) {
        } else {
            $deprecated = '';
            $autoload   = 'no';
            add_option($this->option_name, $newvalue, $deprecated, $autoload);
        }

        // default tag
        if (get_option($this->tag_name)) {
        } else {
            $deprecated = '';
            $autoload   = 'no';
            add_option($this->tag_name, 'blist', $deprecated, $autoload);
        }
    }

    /**
     * Added js and css.
     */
    public function added_scripts()
    {
        $version = '1.0.35';

        if (array_key_exists('page', $_GET) && in_array(sanitize_text_field($_GET['page']), ['blv_settings'])) {
            $dir = plugin_dir_url(__FILE__);

            wp_enqueue_script('blv-plugin-script', $dir.'js/script.js', ['jquery', 'jquery-ui-autocomplete'], $version);
            wp_enqueue_script('blv-plugin-script-pagination', $dir.'js/assets/pagination.min.js', [], $version);
            wp_enqueue_script('blv-plugin-script-bootstrap', $dir.'js/assets/bootstrap.min.js', [], $version);

            wp_enqueue_style('blv-plugin-style-bootstrap-io', $dir.'/css/bootstrap-iso.css', false, $version);
            wp_enqueue_style('blv-plugin-style', $dir.'/css/styles.css', false, $version);
            wp_enqueue_style('blv-plugin-style-overlay', $dir.'/css/overlay.css', false, $version);

            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
        }
    }

    /**
     * Allow for custom query variables.
     */
    public function query_vars($query_vars)
    {
        $query_vars[] = 'blv_settings';

        return $query_vars;
    }

    /**
     * Parse the request.
     */
    public function parse_request(&$wp)
    {
        if (array_key_exists('blv_settings', $wp->query_vars)) {
            $this->blv_settings_func();
            exit;
        }
    }

    /**
     * Settings.
     */
    public function blv_settings_func()
    {
        $page  = 'main';
        $pages = ['privacy', 'button', 'key'];
        if (array_key_exists('type', $_GET)) {
            if (in_array(sanitize_text_field($_GET['type']), $pages)) {
                $page = sanitize_text_field($_GET['type']);
            }
        }

        switch ($page) {
            case 'privacy':
                $content = $this->get_privacy_page();
                break;

            case 'button':
                $content = $this->get_button_page();
                break;

            case 'key':
                $content = $this->get_key_page();
                break;

            case 'main':
            default:
                $content = $this->get_main_page();
                break;
        }

        echo $content;
    }

    private function get_main_page()
    {
        $key       = get_option($this->option_name);
        $keyDetail = $backButton = $instructions = '';

        // header
        $str = '
            <div class="Bl-Plugin__Header">
                <div class="Bl-Plugin__Logo">
                    <span>
                        <img src="'.plugin_dir_url(__FILE__).'img/logo.webp">
                    </span>
                </div>
                <div class="Bl-Plugin__Title">
                    <h2 class="title">Licensify</h2>
                </div>
            </div>
        ';

        if ($key) {
            $api     = new BeautyLicenseVerificationAPI();
            $res     = $api->getKeyDetail(trim($key));

            $success = false;
            if (!$res['success']) {
                $keyDetail = '
                    <p>Invalid API key entered or another API key environment specified.</p>
                    <p>Please enter correct API key or contact info@beauticianlist.com</p>
                ';
            } else {
                $success   = true;

                $keyDetail = '
                    <p>You can manage you key details here <a target="_blank" href="https://dashboard.licensify.io">https://dashboard.licensify.io</a></p>
                    <p>Remove my <a target="_blank" href="https://dashboard.licensify.io">Brand profile</a></p>
                ';
            }
        }

        // content
        $str .= '
            <div class="bootstrap-bl">
                <div class="Bl-Page__Content">
                    <h4 class="Bl-Heading text-left">Your Dashboard</h4>
                    <div class="Bl-Card card">
                        <div class="Bl-Card__Section card-body">
                            <div class="mb-3">
                                <h5 class="text-left">API key</h5>
                                <input class="form-control" type="text" data-element="apikey" value="'.$key.'" placeholder="Input your key here" value="">
                            </div>
        
                            <div class="text-right mb-3">
                                <button type="submit" class="btn btn-primary" data-element="beauty_pro_save_key" data-href="'.admin_url('admin-post.php').'" value="">Save</button>
                            </div>
                            <div data-element="beauty_pro_save_key_message"><div class="mb-3">'.$keyDetail.'</div></div>
                        </div>
                    </div>
                </div>
            </div>
        ';

        if ($success) {
            $str .= '
                <div class="bootstrap-bl">
                    <div class="Bl-Page__Content">
                        <div class="Bl-Layout">
                            <div class="Bl-Layout__Section Bl-Layout__Section--oneThird">
                                <div class="Bl-TextContainer">
                                    <a href="?page=blv_settings&type=priva`cy">
                                        <img src="'.plugin_dir_url(__FILE__).'img/login.png">
                                    </a>
                                    <h5 class="title">
                                        <a href="?page=blv_settings&type=privacy">Privacy</a>
                                    </h5>
                                    <p>Set what products are available exclusively <br> for Beauty Professionals.</p>
                                </div>
                            </div>
                            <div class="Bl-Layout__Section Bl-Layout__Section--oneThird">
                                <div class="Bl-TextContainer">
                                    <a href="?page=blv_settings&type=button">
                                        <img src="'.plugin_dir_url(__FILE__).'img/button.png">
                                    </a>
                                    <h5 class="title">
                                        <a href="?page=blv_settings&type=button">Button</a>
                                    </h5>
                                    <p>Add Beauty Pro Verification button anywhere<br> on your store</p>
                                </div>
                            </div>
                            <div class="Bl-Layout__Section Bl-Layout__Section--oneThird">
                                <div class="Bl-TextContainer">
                                    <a href="?page=blv_settings&type=key">
                                        <img src="'.plugin_dir_url(__FILE__).'img/button.png">
                                    </a>
                                    <h5 class="title">
                                        <a href="?page=blv_settings&type=key">Spam protection</a>
                                    </h5>
                                    <p>Add Google key to protect from spam</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
        }

        // footer
        $str .= '</div>';

        return $str;
    }

    private function get_button_page()
    {
        $str = '
            <div class="Bl-Plugin__Header">
                <div class="Bl-Plugin__Logo">
                    <span>
                        <img src="'.plugin_dir_url(__FILE__).'img/logo.webp">
                    </span>
                </div>
                <div class="Bl-Plugin__Title">
                    <h2 class="title">Licensify</h2>
                </div>
            </div>

            <div class="bootstrap-bl">
                <div class="Bl-Page__Content">   
                    <a href="'.admin_url('admin.php').'?page=blv_settings" class="card-link">< Dashboard</a>
                    <br>
                    <br>
                    <h4 class="Bl-Heading text-left">Button</h4>
                    <div class="Bl-Card card">
                        <div class="Bl-Card__Section card-body">
                            <h6 class="card-title">Adding Beauty Pro Button will allow professionals to log on and upon verification, see discounted pricing or professional only products anywhere within your store.</h6>
                            <p>You can embed Beauty Pro login/registration button anywhere on the store.</p>
                            <p>Simply copy button code and paste anywhere on the page content - static page, product page, OR in your theme file, where you want to show the button.</p>
                        </div>
                    </div>

                    <div class="Bl-Card card card-mc">
                        <div class="Bl-Card__Section card-body card-form">
                            <div class="mb-3">
                                <p>Appearance Settings:</p>
                                <div class="Bl-Layout">
                                    <div class="Bl-Layout__Section Bl-Layout__Section--oneThird">
                                        <div class="Bl-TextContainer">
                                            <div class="mb-3">
                                                <p>Button Text</p>
                                                <input class="form-control" type="text" data-element="beauty_pro_button_text" value="BEAUTY PRO">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="Bl-Layout__Section Bl-Layout__Section--oneThird">
                                        <div class="Bl-TextContainer">
                                            <div class="mb-3">
                                                <p>Text Font Size</p>
                                                <select class="form-control" data-element="beauty_pro_button_size">
                                                    <option value="8">8</option>
                                                    <option value="9">9</option>
                                                    <option value="10">10</option>
                                                    <option value="11">11</option>
                                                    <option value="12">12</option>
                                                    <option selected value="14">14</option>
                                                    <option value="16">16</option>
                                                    <option value="18">18</option>
                                                    <option value="20">20</option>
                                                    <option value="22">22</option>
                                                    <option value="24">24</option>
                                                    <option value="26">26</option>
                                                    <option value="28">28</option>
                                                    <option value="36">36</option>
                                                    <option value="48">48</option>
                                                    <option value="72">72</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="Bl-Layout">
                                    <div class="Bl-Layout__Section Bl-Layout__Section--oneThird">
                                        <div class="Bl-TextContainer">
                                            <div class="mb-3">
                                                <p>Alignment</p>
                                                <select class="form-control" data-element="beauty_pro_button_alignment">
                                                    <option value="left">left</option>
                                                    <option selected value="center">center</option>
                                                    <option value="right">right</option>
                                                    <option value="justify">justify</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="Bl-Layout__Section Bl-Layout__Section--oneThird Bl-Layout__Section--inline">
                                        <div class="Bl-TextContainer Bl-Layout__Section--oneTwin mr-3">
                                            <div class="mb-3">
                                                <p>Text Font Color</p>
                                                <input type="text" data-element="beauty_pro_button_text_color" value="#ffffff" class="form-control color-field" data-default-color="#ffffff" />
                                            </div>
                                        </div>
                                        <div class="Bl-TextContainer Bl-Layout__Section--oneTwin mr-3">
                                            <div class="mb-3">
                                                <p>Background Color</p>
                                                <input type="text" data-element="beauty_pro_button_background_color" value="#ff0000" class="form-control color-field" data-default-color="#ff0000" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="Bl-Layout">
                                    <div class="Bl-Layout__Section">
                                        <div class="Bl-TextContainer ">
                                            <div class="mb-3">
                                                <p>Preview:</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3" data-element="beauty_pro_button_preview">
                                    <div style="text-align: center;">
                                        <button type="button" class="com--beauty-pro"><span data-node="label">BEAUTY PRO</span></button>
                                        <style type="text/css">
                                            .com--beauty-pro { border-radius: 2px; background-color: #ff0000; color: #ffffff; font-size: 14px } 
                                            .com--beauty-pro .com--logout { border-bottom: 1px solid #ffffff }
                                        </style>
                                    </div>
                                </div>
                                <div class="mb-5"><textarea data-element="beauty_pro_button_result" class="form-control">[beauty-license-verification-button]</textarea></div>
                                <div class="Bl-Layout">
                                    <div class="Bl-Layout__Section">
                                        <div class="Bl-TextContainer ">
                                            <div class="mb-3">
                                                <button data-element="beauty_pro_button_copy" class="btn btn-primary">Copy button code</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="Bl-Layout__Section">
                                        <div class="Bl-TextContainer ">
                                            <div class="mb-3">
                                            <button data-element="beauty_pro_button_reset" class="btn btn-secondary">Reset styles</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';

        return $str;
    }

    private function get_privacy_page()
    {
        if (!class_exists('WooCommerce')) {
            // you don't appear to have WooCommerce activated

            $str = '
                <div class="Bl-Plugin__Header">
                    <div class="Bl-Plugin__Logo">
                        <span>
                            <img src="'.plugin_dir_url(__FILE__).'img/logo.webp">
                        </span>
                    </div>
                    <div class="Bl-Plugin__Title">
                        <h2 class="title">Licensify</h2>
                    </div>
                </div>

                <div class="bootstrap-bl">
                    <div class="Bl-Page__Content">   
                        <a href="'.admin_url('admin.php').'?page=blv_settings" class="card-link">< Dashboard</a>
                        <br>
                        <br>
                        <h4 class="Bl-Heading text-left">Privacy</h4>
                        <div class="Bl-Card card">
                            <div class="Bl-Card__Section card-body">
                                <h6 class="card-title">This plugin requires WooCommerce module to be present on your website. Please install and activate WooCommerce plugin.</p>
                            </div>
                        </div>
                    </div>
                </div>
                ';

            return $str;
        }

        $data = $this->prepare_privacy($_GET);

        $search = '';
        if (array_key_exists('search', $_GET)) {
            $search = sanitize_text_field($_GET['search']);
        }

        $str = '
            <div class="Bl-Plugin__Header">
                <div class="Bl-Plugin__Logo">
                    <span>
                        <img src="'.plugin_dir_url(__FILE__).'img/logo.webp">
                    </span>
                </div>
                <div class="Bl-Plugin__Title">
                    <h2 class="title">Licensify</h2>
                </div>
            </div>

            <div class="bootstrap-bl">
                <div class="Bl-Page__Content">   
                    <a href="'.admin_url('admin.php').'?page=blv_settings" class="card-link">< Dashboard</a>
					<br>
					<br>
                    <h4 class="Bl-Heading text-left">Privacy</h4>
                    <div class="Bl-Card card">
                        <div class="Bl-Card__Section card-body">
                            <h6 class="card-title">Do you have products that you wish to sell to professionals only?</h6>
                            <p>You can tag individual products to be available for purchase only to verified professionals.
                            <br>Only Beauty Pros will be able to add those products to the cart.</p>
                        </div>
                    </div>

                    <div class="Bl-Card card">
                        <div class="Bl-Card__Section card-body">
                            <div class="text-right mb-3">
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#beauty_pro_add_tag" data-href="'.admin_url('admin-post.php').'">
                                    Tag Beauty Pro Only Products
                                </button>
                            </div>
                            <div class=" mb-3">
                                <input class="form-control" type="text" data-element="beauty_pro_search_product" placeholder="Search products" value="'.$search.'">
                            </div>';

        if (!$data['with']) {
            $str .= '
                <!-- Empty Result -->
                <div class="Bl-ResourceList__EmptySearchResultWrapper">
                    <div class="Bl-Stack Bl-Stack--vertical Bl-Stack--alignmentCenter">
                        <div class="Bl-Stack__Item">
                            <img style="width:178px" src="'.plugin_dir_url(__FILE__).'img/search.svg">
                        </div>
                        <div class="Bl-Stack__Item">
                            <h4 class="text-center">No products found</h4>
                            <p class="text-center">Try changing the filters or search term</p>
                        </div>
                    </div>
                </div>
            ';
        } else {
            $str .= '
                <!-- List Result -->
                <div class="Bl-ResourceList__Tags mb-3">
                    <ul class="list-group list-group-flush">
            ';
            foreach ($data['with'] as $id => $name) {
                $str .= '
                    <!-- List Result -->
                    <li class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <h2 class="title">'.$name.'</h2>
                        <a data-id="'.$id.'" data-element="beauty_pro_remove_tag" data-href="'.admin_url('admin-post.php').'" href="#" class="btn btn-outline-secondary btn-sm" tabindex="-1" role="button">Remove tag</a>
                    </li>
                ';
            }

            $str .= '
                    </ul>
                </div>
            ';

            $limit = '';
            if (array_key_exists('limit', $_GET)) {
                $limit = sanitize_text_field($_GET['limit']);
            }

            $str .= '
				<label for="limit">Rows per page:</label>
                <select data-element="beauty_pro_select_limit" id="limit">
                    <option '.('5' == $limit ? 'selected' : '').' value="5">5</option>
                    <option '.('10' == $limit ? 'selected' : '').' value="10">10</option>
                    <option '.('25' == $limit ? 'selected' : '').' value="25">25</option>
                </select>
				<p>'.$data['start'].' - '.$data['end'].' of '.$data['count'].'</p>
                <!-- Navigation -->
                <nav aria-label="Page navigation">
                  <ul class="pagination justify-content-center">
                    <li class="page-item '.(1 == $data['page'] ? 'disabled' : '').'">
                        <a class="page-link" href="#" data-page="'.(1 == $data['page'] ? '' : $data['page'] - 1).'" data-element="beauty_pro_go_to">Previous</a>
                    </li>
                    <li class="page-item"><a class="page-link" href="#">'.$data['page'].'</a></li>
                    <li class="page-item '.($data['max'] == $data['page'] ? 'disabled' : '').'">
                        <a class="page-link" href="#" data-page="'.($data['max'] == $data['page'] ? '' : $data['page'] + 1).'" data-element="beauty_pro_go_to">Next</a>
                    </li>
                  </ul>
                </nav>
            ';
        }

        $without = [];
        foreach ($data['without'] as $k => $item) {
            $el = ['name' => $k];
            if ($item['url']) {
                $el['image'] = $item['url'];
            }
            $el['id']  = $item['id'];
            $without[] = $el;
        }

        $source = htmlspecialchars(json_encode($without));

        $str .= '
            <!-- Modal -->
            <div class="bootstrap-bl">
                <div class="modal fade" id="beauty_pro_add_tag" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="exampleModalCenterTitle">Add products</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <input class="form-control" data-element="beauty_pro_add_list" placeholder="Search products">
                                <div class="Bl-ResourceList__Tags mb-3">
                                    <div id="data-container"></div>
                                    <div id="pagination-container" data-url="'.admin_url('admin-post.php').'" data-source="'.$source.'"></div>
                                    <div data-element="beauty_pro_empty_search" class="display-none">
                                        <div class="Bl-ResourceList__EmptySearchResultWrapper">
                                            <div class="Bl-Stack Bl-Stack--vertical Bl-Stack--alignmentCenter">
                                                <div class="Bl-Stack__Item">
                                                    <img style="width:178px" src="'.plugin_dir_url(__FILE__).'img/search.svg">
                                                </div>
                                                <div class="Bl-Stack__Item">
                                                    <h4 class="text-center">No products found</h4>
                                                    <p class="text-center">Try changing the filters or search term</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer"></div>
                        </div>
                    </div>
                </div>
            </div>
        ';

        $str .= '
                        </div>
                    </div>
                 </div>
            </div>
        ';

        return $str;
    }

    private function get_key_page()
    {
        $key       = get_option($this->option_google_name);
        $secret    = get_option($this->option_google_secret);
        if ($secret) {
            $secret = str_repeat('*', 40);
        }

        $str = '
            <div class="Bl-Plugin__Header">
                <div class="Bl-Plugin__Logo">
                    <span>
                        <img src="'.plugin_dir_url(__FILE__).'img/logo.webp">
                    </span>
                </div>
                <div class="Bl-Plugin__Title">
                    <h2 class="title">Licensify</h2>
                </div>
            </div>

            <div class="bootstrap-bl">
                <div class="Bl-Page__Content">   
                    <a href="'.admin_url('admin.php').'?page=blv_settings" class="card-link">< Dashboard</a>
                    <br>
                    <br>
                    <h4 class="Bl-Heading text-left">Spam protection</h4>
                    <div class="Bl-Card card">
                        <div class="Bl-Card__Section card-body">
                            <h6 class="card-title">Adding Google Recaptcha v2 key and secret to protect forms from spam.</h6>
                        </div>
                    </div>

                    <div class="Bl-Card card card">
                        <div class="Bl-Card__Section card-body">
                            <div class="mb-3">
                                <p>Input Recaptcha Site Key:</p>
                                <div class="mb-5">
                                    <input type="text" data-element="beauty_pro_google_key" class="form-control" value="'.$key.'"></input>
                                </div>
                                <p>Input Recaptcha Secret Key:</p>
                                <div class="mb-5">
                                    <input type="text" data-element="beauty_pro_google_secret" class="form-control" value="'.$secret.'"></input>
                                </div>
                                <div class="Bl-Layout">
                                    <div class="Bl-Layout__Section">
                                        <div class="Bl-TextContainer">
                                            <div class="mb-3">
                                                <button data-element="beauty_pro_save_google_key" data-href="'.admin_url('admin-post.php').'" class="btn btn-primary">Save key</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div data-element="beauty_pro_save_google_key_message"><div class="mb-3"></div></div>
                        </div>
                    </div>
                </div>
            </div>';

        return $str;
    }

    private function prepare_privacy($params)
    {
        $search = '';
        if (array_key_exists('search', $params)) {
            $search = sanitize_text_field($params['search']);
        }

        $db    = new BeautyLicenseVerificationDB();
        $items = $db->getAllProducts($search);

        $page  = $start  = $maxPages  = $max  = 1;
        $limit = $end = 5;
        if ($items['with']) {
            if (array_key_exists('limit', $params)) {
                $limit = sanitize_text_field($params['limit']);
                $end   = $limit;
            }
            if (array_key_exists('pagenum', $params)) {
                $page  = sanitize_text_field($params['pagenum']);
                $start = $limit * ($page - 1) + 1;
                $end   = $limit * $page;
            }

            $max      = count($items['with']);
            $maxPages = ceil($max / $limit);

            if ($end > $max) {
                $end = $max;
            }

            $items['with'] = array_slice($items['with'], $limit * ($page - 1), $limit, true);
        }

        $result = [
            'start'   => $start,
            'end'     => $end,
            'max'     => $maxPages,
            'count'   => $max,
            'page'    => $page,
            'with'    => $items['with'],
            'without' => $items['without'],
        ];

        return $result;
    }

    public function handle_beauty_pro_save_key()
    {
        if (!current_user_can('manage_options')) {
            return true;
        }

        if (!array_key_exists('action', $_POST) || 'beauty_pro_save_key' != sanitize_text_field($_POST['action'])) {
            return false;
        }

        $result = ['success' => true, 'message' => '<p>API Key saved!</p>'];

        $key = trim(sanitize_text_field($_POST['key']));
        $api = new BeautyLicenseVerificationAPI();
        $res = $api->getKeyDetail($key);
        if (!$res['success']) {
            $result['success'] = false;
            // $result['message'] = $res['error'];
            $result['message'] = '
                <p>Invalid API key entered or another API key environment specified.</p>
                <p>Please enter correct API key or contact <a href="mailto:info@beauticianlist.com">info@beauticianlist.com</a></p>
            ';
        } else {
            update_option($this->option_name, $key);
        }

        echo json_encode($result);
    }

    public function handle_beauty_pro_remove_tag()
    {
        if (!current_user_can('manage_options')) {
            return true;
        }

        if (!array_key_exists('action', $_POST) || 'beauty_pro_remove_tag' != sanitize_text_field($_POST['action'])) {
            return false;
        }

        $db = new BeautyLicenseVerificationDB();
        $db->removeTag($_POST['id']);

        echo json_encode(['success' => true]);
    }

    public function handle_beauty_pro_add_tag()
    {
        if (!current_user_can('manage_options')) {
            return true;
        }

        if (!array_key_exists('action', $_POST) || 'beauty_pro_add_tag' != sanitize_text_field($_POST['action'])) {
            return false;
        }

        $db = new BeautyLicenseVerificationDB();
        $db->addTag($_POST['id']);

        echo json_encode(['success' => true]);
    }

    public function handle_beauty_pro_save_google_key()
    {
        if (!current_user_can('manage_options')) {
            return true;
        }

        if (!array_key_exists('action', $_POST) || 'beauty_pro_save_google_key' != sanitize_text_field($_POST['action'])) {
            return false;
        }

        $result = ['success' => true, 'message' => '<p>Key saved!</p>'];

        $key = trim(sanitize_text_field($_POST['key']));
        update_option($this->option_google_name, $key);

        $key = trim(sanitize_text_field($_POST['secret']));
        update_option($this->option_google_secret, $key);

        echo json_encode($result);
    }
}

// Instantiate a singleton of this plugin
$ba = new BeautyLicenseVerification();
