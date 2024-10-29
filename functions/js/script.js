var modalParams = {},
    BLConfig = {},
    captchaIDs = {};

(function ($) {
    $(document).ready(function () {

        var el = $('.color-field');
        if (el.length) {
            el.wpColorPicker({
                palettes: false,
                change: function (event, ui) {
                    updateShortcode();
                },
                clear: function (event, ui) {
                    updateShortcode();
                },
            });
        }

        // open tos popup
        if ($('#beauty_pro_popup').length) {
            $('#beauty_pro_popup').modal('show');
        }

        $('#beauty_pro_signup_login_popup').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var params = button[0].getAttribute('data-params');
            if (params) {
                var decoded = {};
                try {
                    decoded = JSON.parse(params)
                } catch (e) { console.log(e) }
                modalParams = decoded;
            }

            BLConfig = $('#beauty_pro_signup_login_popup').data('config');

            addCaptcha('registration_salon')
            addCaptcha('registration_tech')
            countryState();
        });

        var countryState = function () {
            $('*[data-element="beauty_pro_verification_country"]').on('change', function () {
                var form = $(this).parent().parent(),
                    state = form.find('*[data-element="beauty_pro_verification_state"]'),
                    stateDiv = $(state).parent(),
                    imageElement = form.find('*[data-element="beauty_pro_verification_license_image"]'),
                    salonImageElement = form.find('*[data-element="beauty_pro_salon_verification_license_image"]');

                if ('usa' == this.value) {
                    stateDiv.show();

                    // hide image upload
                    imageElement.addClass('display-none');
                    salonImageElement.addClass('display-none');
                    var file = form.find('*[data-element="beauty_pro_verification_license_image"] #image').get(0);
                    if (file) $(file).val('');

                    file = form.find('*[data-element="beauty_pro_salon_verification_license_image"] #image').get(0);
                    if (file) $(file).val('');

                } else {
                    stateDiv.hide();

                    // display image upload
                    imageElement.removeClass('display-none');
                    salonImageElement.removeClass('display-none');
                }
            })
        }

        $('#beauty_pro_signup_login_popup').on('hidden.bs.modal', function () {
            setActive('signup')

            $('#beauty_pro_signup_login_popup input:not([type=hidden])').val('')
            $('#beauty_pro_signup_login_popup textarea:not([type=hidden])').val('')

            $('*[data-element="beauty_pro_signup_step2_popup_div"] input[name="image"]').val('');
        });

        $('*[data-element="beauty_pro_signup_login_popup"]').click(function () {
            setActive('login')
        });

        $('*[data-element="beauty_pro_signup_step2_speciality"]').click(function () {
            $('*[data-element="beauty_pro_signup_step2_speciality"]').not(this).prop('checked', false);
            var val = $(this).val();
            var ischecked = $(this).is(':checked');
            var tables = $('*[data-element="beauty_pro_signup_step2_speciality_table"]');
            for (var table of tables) {
                if ($(table).attr('data-val') == val) {
                    if (ischecked) {
                        table.style.display = 'block';
                    } else {
                        table.style.display = 'none';
                    }
                } else {
                    table.style.display = 'none';
                }
            }
        });

        $('*[data-element="beauty_pro_login_popup"]').click(function () {
            setActive('login')

            $('*[data-element="beauty_pro_signup_step2_popup_div"] input[name="image"]').val('');
        });

        $('*[data-element="beauty_pro_signup_popup"]').click(function () {
            addCaptcha('registration_salon')
            addCaptcha('registration_tech')
            setActive('signup')
        });

        $('*[data-element="beauty_pro_login_forgot_popup"]').click(function (event) {
            event.stopPropagation();

            setActive('forgot')
        });

        $('*[data-element="beauty_pro_support_popup"]').click(function (event) {
            event.stopPropagation();

            addCaptcha('support')
            setActive('support')
        });

        $('*[data-element="beauty_pro_international_popup"]').click(function (event) {
            event.stopPropagation();

            addCaptcha('international')
            setActive('international')
        });

        $('*[data-element="beauty_pro_add_tag"]').click(function () {
            var popup = $('*[data-element="beauty_pro_add_tag_div"]');
            popup[0].style.display = 'block';
        });

        $('*[data-element="beauty_pro_tos"]').click(function (event) {
            event.stopPropagation();
            var form = event.target.form;
            $(form).removeClass('error');
            var formData = new FormData(form);

            var errors = {
                confirm: 'This field is required.'
            };

            for (var [key, el] of formData.entries()) {
                if (el && undefined !== errors[key]) {
                    delete errors[key];
                }
            }

            var formEl = $('#beauty_pro_popup #confirmTOS');
            var hint = $('*[data-element="beauty_pro_tos_error_confirmTOS"]').get(0);
            if (undefined !== errors['confirm']) {
                $(formEl).addClass('error');
                $(formEl).closest(".form-group").find(".form-control").addClass('error');
                if (hint) {
                    $(hint).removeClass('display-none');
                    $(hint).text(errors['confirm']);
                }
                return;
            } else {
                $(formEl).removeClass('error');
                $(formEl).closest(".form-group").find(".form-control").removeClass('error');
                $(hint).addClass('display-none');
            }

            $('.modal').addClass('overlay preloader')

            $.ajax({
                cache: false,
                dataType: 'json',
                url: $(form).attr('action'),
                type: "POST",
                data: $(form).serialize(),
                success: function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        //$('*[data-element="beauty_pro_update_error"]').text(data.errors.message);
                    }
                    $('.modal').removeClass('overlay preloader')
                },
                error: function (err) {
                    $('.modal').removeClass('overlay preloader')
                }
            })
        })


        $('*[data-element="beauty_pro_signup"]').click(function (event) {
            event.stopPropagation();
            var form = event.target.form;
            $(form).removeClass('error');
            var formData = new FormData(form);

            var errors = {
                'beauty_pro_signup_step1_radio': 'This field is required.',
                'speciality[]': 'This field is required.',
                confirm: 'This field is required.'
            };

            for (var [key, el] of formData.entries()) {
                if (el && undefined !== errors[key]) {
                    delete errors[key];
                }
            }

            if (errors && Object.entries(errors).length) {
                return setVerificationErrorsStep2(errors);
            }

            $('.modal').addClass('overlay preloader')
            $.ajax({
                cache: false,
                dataType: 'json',
                url: $(form).attr('action'),
                type: "POST",
                processData: false,
                contentType: false,
                data: formData,
                success: function (data) {
                    if (data.success) {
                        if (data.hasOwnProperty('step') && data.step) {
                            $('#beauty_pro_signup_login_popup input:not([type=hidden])').val('')
                            if (data.hasOwnProperty('messages') && data.messages) {
                                var str = '<p>Thank you for signing up!</br>' + data.messages.join('</br>') + '</p>';
                                $('*[data-element="beauty_pro_notify_popup_message"]').html(str);
                            }
                            setActive(data.step)
                        } else {
                            var defaultAction = true;

                            if (modalParams && Object.keys(modalParams).length) {
                                if (modalParams.hasOwnProperty('callback')) {
                                    defaultAction = false;

                                    window[modalParams.callback]($(form).serializeArray(), data);
                                }
                            }

                            if (defaultAction) {
                                location.reload();
                            }

                            //location.reload();
                        }
                    } else {
                        $('*[data-element="beauty_pro_signup_error"]').text(data.errors.message);
                    }

                    $('.modal').removeClass('overlay preloader')
                },
                error: function (err) {
                    $('.modal').removeClass('overlay preloader')
                }
            })
        });

        $('*[data-element="beauty_pro_verification_try"]').click(function (event) {
            setActive('signup')
        })

        $('*[data-element="beauty_pro_verification_support"]').click(function (event) {
            setActive('support')
        })

        $('*[data-element="beauty_pro_update_try"]').click(function (event) {
            setActive('update')
        })

        $('*[data-element="beauty_pro_update_continue"]').click(function (event) {
            var form = $('*[data-element="beauty_pro_update_step2_popup_div"] form')[0];
            $('.modal').addClass('overlay preloader')

            $.ajax({
                cache: false,
                dataType: 'json',
                url: $(form).attr('action'),
                type: "POST",
                data: $(form).serialize(),
                success: function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        $('*[data-element="beauty_pro_update_error"]').text(data.errors.message);
                    }
                    $('.modal').removeClass('overlay preloader')
                },
                error: function (err) {
                    $('.modal').removeClass('overlay preloader')
                }
            })
        })

        $('*[data-element="beauty_pro_verification"]').click(function (event) {
            event.stopPropagation();

            if (!validateCaptcha('registration_tech')) {
                $('*[data-element="beauty_pro_verification_error"]').text('Please verify that you\'re not a robot.');
                return;
            } else {
                $('*[data-element="beauty_pro_verification_error"]').text('');
            }

            var form = event.target.form;
            $(form).removeClass('error');
            var formData = new FormData(form), formObjects = {};
            for (var key of formData.keys()) {
                formObjects[key] = formData.get(key);
            }
            var file = $('*[data-element="beauty_pro_signup_popup_div"] div#individual *[data-element="beauty_pro_verification_license_image"] #image').get(0);
            if (file && file.files) {
                if (undefined !== file.files[0]) {
                    formData.append('image', file.files[0]);
                }
            }

            var errors = {
                //firstname: 'This field is required.',
                lastname: 'This field is required.',
                email: 'This field is required.',
                state: 'This field is required.',
                license: 'This field is required.'
            };

            for (var [key, el] of formData.entries()) {
                if (el && undefined !== errors[key]) {
                    delete errors[key];
                } else {
                    if ('shortstate' == key) {
                        let excludedArr = BLConfig['manual-states']; //['ar', 'ms', 'ok'];
                        if (excludedArr.indexOf(el.toUpperCase()) != -1) {
                            if (!file || !file.files.length) {
                                errors['image'] = 'This field is required.';
                            }
                        }
                    }
                }
            }

            var countrySelect = $('*[data-element="beauty_pro_signup_popup_div"] div#individual select');
            if (!countrySelect.length) {
                var country = $('*[data-element="beauty_pro_signup_popup_div"] div#individual *[name="country"]')[0].value;
            } else {
                var country = countrySelect[0].value;
            }
            if ('usa' != country) {
                delete errors['state'];
                if (!file.files.length) {
                    errors['image'] = 'This field is required.';
                }
            }

            if (errors && Object.entries(errors).length) {
                return setVerificationErrors(errors);
            }

            $('.modal').addClass('preloader-logo')
            $('.modal').append('<div class="com__overlay pt__overlay transition-ease theme-logo"><div class="overlay__spinner is-show"></div><div class="overlay__content is-show"><div class="inner">Your license is being verified.<br>Please do not refresh screen or go back.</div></div></div>');

            $.ajax({
                cache: false,
                dataType: 'json',
                url: $(form).attr('action'),
                type: "POST",
                processData: false,
                contentType: false,
                data: formData,
                success: function (data) {
                    var popup = $('*[data-element="beauty_pro_signup_popup_div"]'),
                        popup1 = $('*[data-element="beauty_pro_signup_step_1_1_popup_div"]');
                    if (data.success && data.data.length > 0) {
                        var text = '<h2>Finish Registration</h2>';

                        $('*[data-element="beauty_pro_verification_error"]').html('');
                        if (data.data[0]) {
                            var notifyFlag = false,
                                license = data.data[0],
                                excludedArr = BLConfig['manual-states']; // ['ar', 'ms', 'ok'];

                            if ('usa' != country) {
                                notifyFlag = true;
                            } else {
                                var shortState = license.state.toUpperCase();
                                if (excludedArr.indexOf(shortState) != -1) {
                                    notifyFlag = true;
                                }
                            }

                            var hint = $('*[data-element="beauty_pro_signup_step_1_1_hint"]');
                            hint = $(hint[0]);
                            if (notifyFlag) {
                                $('*[data-element="beauty_pro_verification_try"]').parent().removeClass('display-none');
                                $('*[data-element="beauty_pro_verification_support"]').parent().removeClass('display-none');

                                $('*[data-element="beauty_pro_signup_error_confirm"]').parent().addClass('display-none');
                                $('*[data-element="beauty_pro_signup_error_speciality_div"]').parent().addClass('display-none');

                                $('*[data-element="beauty_pro_signup"]').parent().addClass('display-none');

                                text = '<h1>Oh no!</h1>';
                                text += '<p>We need additional information in order to verify your license.</p>';
                                text += '<p>A member of our team will be reaching out to you shortly using the email provided.</p>';

                                hint.html(text);

                                popup[0].style.display = 'none';
                                popup1[0].style.display = 'block';

                                $('.modal').removeClass('overlay preloader preloader-logo');
                                $('.com__overlay').remove();
                                return false;
                            }

                            $('*[data-element="beauty_pro_verification_try"]').parent().addClass('display-none');
                            $('*[data-element="beauty_pro_verification_support"]').parent().addClass('display-none');

                            $('*[data-element="beauty_pro_signup"]').parent().removeClass('display-none');

                            hint.html(text);

                            var step2Items = ['firstname', 'lastname', 'state', 'number', 'image', 'expired', 'type'];
                            for (var el of step2Items) {
                                if (license.hasOwnProperty(el)) {
                                    var input = $('*[data-element="beauty_pro_signup_step_1_1_popup_div"] input[name="' + el + '"]').get(0);
                                    $(input).val(license[el]);
                                }
                            }

                            var input = $('*[data-element="beauty_pro_signup_step_1_1_popup_div"] input[name="email"]').get(0);
                            $(input).val(formData.get('email'));

                            if (excludedArr.indexOf(shortState) == -1) {
                                var html = '', index = 0, all = {};
                                for (var license of data.data) {
                                    html += '<div class="form-group Bl-LicenseItem">';
                                    var step2Items = ['firstname', 'lastname', 'email', 'number', 'image', 'fullname', 'status', 'expired', 'type'];
                                    for (var el of step2Items) {
                                        if (license.hasOwnProperty(el)) {
                                            all[el] = license[el];
                                        }
                                    }
                                    html += '<div class="blist__card__details">';
                                    html += '    <div class="block-license-title">' + license['fullname'] + '</div>';
                                    html += '    <div class="block-attribute"><ul>';

                                    var step2Items = ['number', 'type', 'expired'];
                                    for (var el of step2Items) {
                                        if (license.hasOwnProperty(el)) {
                                            html += '<li>' + license[el] + '</li>';
                                        }
                                    }

                                    html += '            <li><span>' + license['status'] + '</span></li>';
                                    html += '       </ul>';
                                    html += '    </div>';
                                    html += '</div>';
                                    html += '<div class="blist__card__radio">';
                                    html += '   <label class="block-check">';
                                    html += '       <input type="radio" name="beauty_pro_signup_step1_radio" value="' + escapeHtml(JSON.stringify(all)) + '">';
                                    html += '       <span class="checkmark"></span>';
                                    html += '   </label>';
                                    html += '</div>';
                                    html += '</div>';
                                    index++;
                                }

                                $('*[data-element="beauty_pro_signup_step_1_1_popup_div"] form .Bl-LicenseList').html(html);

                                var el = $('*[data-element="beauty_pro_signup_step_1_1_popup_div"] form .Bl-LicenseList .Bl-LicenseItem .blist__card__details');
                                if (el.length) {
                                    el.on('click', function (e) {
                                        $(this).parent().find('*[type="radio"]').click()
                                    })
                                }

                                $('*[name="beauty_pro_signup_step1_radio"]').change(function () {

                                    $(this).closest('.Bl-LicenseItem').addClass('is-active');
                                    var license = [];
                                    try {
                                        license = JSON.parse($(this).val());
                                    } catch (e) { }

                                    var step2Items = ['firstname', 'lastname', 'state', 'number', 'image', 'expired', 'type'];
                                    for (var el of step2Items) {
                                        if (license.hasOwnProperty(el)) {
                                            var input = $('*[data-element="beauty_pro_signup_step_1_1_popup_div"] input[name="' + el + '"]').get(0);
                                            $(input).val(license[el]);
                                        }
                                    }
                                });
                            }

                            popup[0].style.display = 'none';
                            popup1[0].style.display = 'block';
                        }
                    } else {
                        $(form).addClass('error');
                        if (data.hasOwnProperty('errors') && -1 !== [1002, 1004, 1010].indexOf(data.errors.code)) {
                            $('*[data-element="beauty_pro_verification_error"]').html(data.errors.message);
                        } else {
                            $('*[data-element="beauty_pro_verification_try"]').parent().removeClass('display-none');
                            $('*[data-element="beauty_pro_verification_support"]').parent().removeClass('display-none');

                            $('*[data-element="beauty_pro_signup_error_confirm"]').parent().addClass('display-none');
                            $('*[data-element="beauty_pro_signup_error_speciality_div"]').parent().addClass('display-none');

                            $('*[data-element="beauty_pro_signup"]').parent().addClass('display-none');

                            var hint = $('*[data-element="beauty_pro_signup_step_1_1_hint"]');
                            hint = $(hint[0]);
                            text = '<h1>Oh no!</h1>';
                            text += '<p>We need additional information in order to verify your license.</p>';
                            text += '<p>A member of our team will be reaching out to you shortly using the email provided.</p>';

                            hint.html(text);

                            popup[0].style.display = 'none';
                            popup1[0].style.display = 'block';
                        }
                    }
                    $('.modal').removeClass('overlay preloader preloader-logo');
                    $('.com__overlay').remove();
                },
                error: function (xhr, status, error) {
                    setVerificationErrors([]);
                    var err = 0;
                    try {
                        err = eval("(" + xhr.responseText + ")");
                    } catch (e) { }

                    // timeout event
                    if (504 == xhr.status) {
                        formObjects['action'] = formObjects['action'] + '_support';
                        $.ajax({
                            cache: false,
                            dataType: "json",
                            url: $(form).attr('action'),
                            type: form.method,
                            data: new URLSearchParams(formObjects).toString(),
                            success: function (data) { }
                        })
                    }

                    if ('error' == status && 0 == err) {
                        $('*[data-element="beauty_pro_verification_error"]').html('<p style="color:red">Unexpected error occurred. Please try again.</p>');
                    }
                    $('.modal').removeClass('overlay preloader preloader-logo');
                    $('.com__overlay').remove();
                }
            });
        });

        $('*[data-element="beauty_pro_salon_verification"]').click(function (event) {
            event.stopPropagation();

            if (!validateCaptcha('registration_salon')) {
                $('*[data-element="beauty_pro_salon_verification_error"]').text('Please verify that you\'re not a robot.');
                return;
            } else {
                $('*[data-element="beauty_pro_salon_verification_error"]').text('');
            }

            var form = event.target.form;
            $(form).removeClass('error');
            var formData = new FormData(form), formObjects = {};
            for (var key of formData.keys()) {
                formObjects[key] = formData.get(key);
            }
            var file = $('*[data-element="beauty_pro_salon_verification_license_image"] #image').get(0);
            if (file && file.files) {
                if (undefined !== file.files[0]) {
                    formData.append('image', file.files[0]);
                }
            }

            var errors = {
                //firstname: 'This field is required.',
                businessname: 'This field is required.',
                email: 'This field is required.',
                state: 'This field is required.',
                license: 'This field is required.'
            };

            for (var [key, el] of formData.entries()) {
                if (el && undefined !== errors[key]) {
                    delete errors[key];
                } else {
                    if ('shortstate' == key) {
                        let excludedArr = BLConfig['manual-states']; // ['ar', 'ms', 'ok'];
                        if (excludedArr.indexOf(el.toUpperCase()) != -1) {
                            if (!file || !file.files.length) {
                                errors['image'] = 'This field is required.';
                            }
                        }
                    }
                }
            }

            var countrySelect = $('*[data-element="beauty_pro_signup_popup_div"] div#salon select');
            if (!countrySelect.length) {
                var country = $('*[data-element="beauty_pro_signup_popup_div"] div#salon *[name="country"]')[0].value;
            } else {
                var country = countrySelect[0].value;
            }
            if ('usa' != country) {
                delete errors['state'];
                if (!file.files.length) {
                    errors['image'] = 'This field is required.';
                }
            }

            if (errors && Object.entries(errors).length) {
                return setSalonVerificationErrors(errors);
            }

            $('.modal').addClass('preloader-logo')
            $('.modal').append('<div class="com__overlay pt__overlay transition-ease theme-logo"><div class="overlay__spinner is-show"></div><div class="overlay__content is-show"><div class="inner">Your license is being verified.<br>Please do not refresh screen or go back.</div></div></div>');

            $.ajax({
                cache: false,
                dataType: 'json',
                url: $(form).attr('action'),
                type: "POST",
                processData: false,
                contentType: false,
                data: formData,
                success: function (data) {
                    if (data.success && data.data.length > 0) {
                        var popup = $('*[data-element="beauty_pro_signup_popup_div"]'),
                            popup1 = $('*[data-element="beauty_pro_signup_step_1_1_popup_div"]'),
                            text = '<h2>Finish Registration</h2>';

                        $('*[data-element="beauty_pro_verification_error"]').html('');
                        if (data.data[0]) {
                            var notifyFlag = false,
                                license = data.data[0],
                                excludedArr = BLConfig['manual-states']; // ['ar', 'ms', 'ok'];

                            if ('usa' != country) {
                                notifyFlag = true;
                            } else {
                                var shortState = license.state.toUpperCase();
                                if (excludedArr.indexOf(shortState) != -1) {
                                    notifyFlag = true;
                                }
                            }

                            var hint = $('*[data-element="beauty_pro_signup_step_1_1_hint"]');
                            hint = $(hint[0]);
                            if (notifyFlag) {
                                $('*[data-element="beauty_pro_verification_try"]').parent().removeClass('display-none');
                                $('*[data-element="beauty_pro_verification_support"]').parent().removeClass('display-none');

                                $('*[data-element="beauty_pro_signup_error_confirm"]').parent().addClass('display-none');
                                $('*[data-element="beauty_pro_signup_error_speciality_div"]').parent().addClass('display-none');

                                $('*[data-element="beauty_pro_signup"]').parent().addClass('display-none');

                                text = '<h1>Oh no!</h1>';
                                text += '<p>We need additional information in order to verify your license.</p>';
                                text += '<p>A member of our team will be reaching out to you shortly using the email provided.</p>';

                                hint.html(text);

                                popup[0].style.display = 'none';
                                popup1[0].style.display = 'block';

                                $('.modal').removeClass('overlay preloader preloader-logo');
                                $('.com__overlay').remove();
                                return false;
                            }

                            $('*[data-element="beauty_pro_verification_try"]').parent().addClass('display-none');
                            $('*[data-element="beauty_pro_verification_support"]').parent().addClass('display-none');

                            $('*[data-element="beauty_pro_signup"]').parent().removeClass('display-none');

                            hint.html(text);

                            var step2Items = ['firstname', 'lastname', 'state', 'number', 'image', 'expired', 'type'];
                            for (var el of step2Items) {
                                if (license.hasOwnProperty(el)) {
                                    var input = $('*[data-element="beauty_pro_signup_step_1_1_popup_div"] input[name="' + el + '"]').get(0);
                                    $(input).val(license[el]);
                                }
                            }

                            var input = $('*[data-element="beauty_pro_signup_step_1_1_popup_div"] input[name="email"]').get(0);
                            $(input).val(formData.get('email'));

                            if (excludedArr.indexOf(shortState) == -1) {
                                var html = '', index = 0, all = {};
                                for (var license of data.data) {
                                    html += '<div class="form-group Bl-LicenseItem">';
                                    var step2Items = ['firstname', 'lastname', 'email', 'number', 'image', 'fullname', 'status', 'expired', 'type'];
                                    for (var el of step2Items) {
                                        if (license.hasOwnProperty(el)) {
                                            all[el] = license[el];
                                        }
                                    }

                                    html += '<div class="blist__card__details">';
                                    html += '    <div class="block-license-title">' + license['fullname'] + '</div>';
                                    html += '    <div class="block-attribute"><ul>';

                                    var step2Items = ['number', 'type', 'expired'];
                                    for (var el of step2Items) {
                                        if (license.hasOwnProperty(el)) {
                                            html += '<li>' + license[el] + '</li>';
                                        }
                                    }

                                    html += '            <li><span>' + license['status'] + '</span></li>';
                                    html += '       </ul>';
                                    html += '    </div>';
                                    html += '</div>';
                                    html += '<div class="blist__card__radio">';
                                    html += '   <label class="block-check">';
                                    html += '       <input type="radio" name="beauty_pro_signup_step1_radio" value="' + escapeHtml(JSON.stringify(all)) + '">';
                                    html += '       <span class="checkmark"></span>';
                                    html += '   </label>';
                                    html += '</div>';
                                    html += '</div>';
                                    index++;
                                }

                                $('*[data-element="beauty_pro_signup_step_1_1_popup_div"] form .Bl-LicenseList').html(html);

                                var el = $('*[data-element="beauty_pro_signup_step_1_1_popup_div"] form .Bl-LicenseList .Bl-LicenseItem .blist__card__details');
                                if (el.length) {
                                    el.on('click', function (e) {
                                        $(this).parent().find('*[type="radio"]').click()
                                    })
                                }

                                $('*[name="beauty_pro_signup_step1_radio"]').change(function () {

                                    $(this).closest('.Bl-LicenseItem').addClass('is-active');
                                    var license = [];
                                    try {
                                        license = JSON.parse($(this).val());
                                    } catch (e) { }

                                    var step2Items = ['firstname', 'lastname', 'state', 'number', 'image', 'expired', 'type'];
                                    for (var el of step2Items) {
                                        if (license.hasOwnProperty(el)) {
                                            var input = $('*[data-element="beauty_pro_signup_step_1_1_popup_div"] input[name="' + el + '"]').get(0);
                                            $(input).val(license[el]);
                                        }
                                    }
                                });
                            }

                            popup[0].style.display = 'none';
                            popup1[0].style.display = 'block';
                        }
                    } else {
                        $(form).addClass('error');
                        var error = '';

                        if (data.hasOwnProperty('errors') && -1 !== [1002, 1004, 1010].indexOf(data.errors.code)) {
                            error = data.errors.message;
                        } else {
                            error = '<p>Your license did not come up as an active license in the state that you selected. This could be due to a technical error, if so please accept our most sincere apologies.  Please allow us to investigate further and get back to you as soon as humanly possible using the information provided.</p>';
                            //$('*[data-element="beauty_pro_verification_error"]').text(data.errors.message);
                        }
                        $('*[data-element="beauty_pro_salon_verification_error"]').html(error);
                    }
                    $('.modal').removeClass('overlay preloader preloader-logo');
                    $('.com__overlay').remove();
                },
                error: function (xhr, status, error) {
                    setSalonVerificationErrors([])
                    var err = 0;
                    try {
                        err = eval("(" + xhr.responseText + ")");
                    } catch (e) { }

                    // timeout event
                    if (504 == xhr.status) {
                        formObjects['action'] = formObjects['action'] + '_support';
                        $.ajax({
                            cache: false,
                            dataType: "json",
                            url: $(form).attr('action'),
                            type: form.method,
                            data: new URLSearchParams(formObjects).toString(),
                            success: function (data) { }
                        })
                    }

                    if ('error' == status && 0 == err) {
                        $('*[data-element="beauty_pro_verification_error"]').html('<p style="color:red">Unexpected error occurred. Please try again.</p>');
                    }
                    $('.modal').removeClass('overlay preloader preloader-logo');
                    $('.com__overlay').remove();
                }
            });
        });

        $('*[data-element="beauty_pro_update_verification"]').click(function (event) {
            event.stopPropagation();
            var form = event.target.form;
            $(form).removeClass('error');
            var formData = new FormData(form);
            var file = $('*[data-element="beauty_pro_update_license_image"] #image').get(0);
            if (file && file.files) {
                if (undefined !== file.files[0]) {
                    formData.append('image', file.files[0]);
                }
            }

            var errors = {
                //firstname: 'This field is required.',
                lastname: 'This field is required.',
                email: 'This field is required.',
                state: 'This field is required.',
                license: 'This field is required.'
            };

            for (var [key, el] of formData.entries()) {
                if (el && undefined !== errors[key]) {
                    delete errors[key];
                } else {
                    if ('shortstate' == key) {
                        let excludedArr = BLConfig['manual-states']; // ['ar', 'ms', 'ok'];
                        if (excludedArr.indexOf(el.toUpperCase()) != -1) {
                            if (!file || !file.hasOwnProperty('files')) {
                                errors['image'] = 'This field is required.';
                            }
                        }
                    }
                }
            }

            if (errors && Object.entries(errors).length) {
                return setUpdateErrors(errors);
            }

            $('.modal').addClass('overlay preloader')
            $.ajax({
                cache: false,
                dataType: 'json',
                url: $(form).attr('action'),
                type: "POST",
                processData: false,
                contentType: false,
                data: formData,
                success: function (data) {
                    $('*[data-element="beauty_pro_update_verfication_error"]').html('');
                    if (data.success && data.data.length > 0) {
                        var popup = $('*[data-element="beauty_pro_update_popup_div"]');
                        var popup1 = $('*[data-element="beauty_pro_update_step_1_1_popup_div"]');
                        var text = 'Thank you for being licensed and supporting the professional community. We welcome you on board!';

                        $('*[data-element="beauty_pro_update_skip"]').parent().removeClass('display-none');
                        $('*[data-element="beauty_pro_update_continue"]').parent().addClass('display-none');

                        $('*[data-element="beauty_pro_update_error"]').html('');
                        if (data.data[0].state) {
                            var license = data.data[0];
                            var shortState = license.state.toUpperCase();
                            let excludedArr = BLConfig['manual-states']; // ['ar', 'ms', 'ok'];
                            if (excludedArr.indexOf(shortState) != -1) {
                                text = '<p>We are in the process of verifying your account and will get back to you within 24-48 hours.</p>';
                                //text += '<p>You can still proceed with registration. Your account will have limited functionality until we are able to approve your application.</p>';
                            }

                            var hint = $('*[data-element="beauty_pro_update_step2_hint"]');
                            hint = $(hint[0]);
                            hint.html(text);

                            var step2Items = ['firstname', 'lastname', 'id', 'state', 'number', 'image', 'expired', 'type'];
                            for (var el of step2Items) {
                                if (license.hasOwnProperty(el)) {
                                    var input = $('*[data-element="beauty_pro_update_step2_popup_div"] input[name="' + el + '"]').get(0);
                                    $(input).val(license[el]);
                                }
                            }

                            if (excludedArr.indexOf(shortState) != -1) {
                                setActive('signupStep2')
                            } else {
                                var html = '', index = 0, all = {};
                                for (var license of data.data) {
                                    html += '<div class="form-group Bl-LicenseItem">';
                                    var step2Items = ['firstname', 'lastname', 'email', 'id', 'number', 'image', 'fullname', 'status', 'expired', 'type'];
                                    for (var el of step2Items) {
                                        if (license.hasOwnProperty(el)) {
                                            all[el] = license[el];
                                        }
                                    }

                                    html += '<div class="blist__card__details">';
                                    html += '    <div class="block-license-title">' + license['fullname'] + '</div>';
                                    html += '    <div class="block-attribute"><ul>';

                                    var step2Items = ['number', 'type', 'expired'];
                                    for (var el of step2Items) {
                                        if (license.hasOwnProperty(el)) {
                                            html += '<li>' + license[el] + '</li>';
                                        }
                                    }

                                    html += '        <li><span>' + license['status'] + '</span></li>';
                                    html += '       </ul>';
                                    html += '    </div>';
                                    html += '</div>';
                                    html += '<div class="blist__card__radio">';
                                    html += '   <label class="block-check">';
                                    html += '        <input type="radio" name="beauty_pro_update_step1_radio" value="' + escapeHtml(JSON.stringify(all)) + '">';
                                    html += '        <span class="checkmark"></span>';
                                    html += '    </label>';
                                    html += '</div>';
                                    html += '</div>';
                                    index++;
                                }

                                $('*[data-element="beauty_pro_update_skip"]').val(escapeHtml(JSON.stringify(data.data[0])));
                                $('*[data-element="beauty_pro_update_step_1_1_popup_div"] form .Bl-LicenseList').html(html);

                                var el = $('*[data-element="beauty_pro_update_step_1_1_popup_div"] form .Bl-LicenseList .Bl-LicenseItem .blist__card__details');
                                if (el.length) {
                                    el.on('click', function (e) {
                                        $(this).parent().find('*[type="radio"]').click()
                                    })
                                }

                                $('*[name="beauty_pro_update_step1_radio"]').change(function () {
                                    $('*[data-element="beauty_pro_update_continue"]').parent().removeClass('display-none');
                                    $(this).closest('.Bl-LicenseItem').addClass('is-active');
                                    var license = [];
                                    try {
                                        license = JSON.parse($(this).val());
                                    } catch (e) { }

                                    var step2Items = ['firstname', 'lastname', 'state', 'number', 'image', 'id'];
                                    for (var el of step2Items) {
                                        if (license.hasOwnProperty(el)) {
                                            var input = $('*[data-element="beauty_pro_update_step2_popup_div"] input[name="' + el + '"]').get(0);
                                            $(input).val(license[el]);
                                        }
                                    }
                                });

                                popup[0].style.display = 'none';
                                popup1[0].style.display = 'block';
                            }
                        }
                    } else {
                        $(form).addClass('error');
                        var error = '';

                        if (data.hasOwnProperty('errors') && -1 !== [1002, 1004].indexOf(data.errors.code)) {
                            error = data.errors.message;
                        } else {
                            error = '<p>Your license did not come up as an active license in the state that you selected. This could be due to a technical error, if so please accept our most sincere apologies.  Please allow us to investigate further and get back to you as soon as humanly possible using the information provided.</p>';
                            //$('*[data-element="beauty_pro_verification_error"]').text(data.errors.message);
                        }
                        $('*[data-element="beauty_pro_update_verfication_error"]').html(error);
                    }
                    $('.modal').removeClass('overlay preloader')
                },
                error: function (err) {
                    $('.modal').removeClass('overlay preloader')
                }
            });
        });

        $('*[data-element="beauty_pro_select_status"]').on('change', function () {
            goTo('status', this.value);
        });

        $('*[data-element="beauty_pro_select_period"]').on('change', function () {
            goTo('period', this.value);
        });

        $('*[data-element="beauty_pro_select_limit"]').on('change', function () {
            goTo('limit', this.value);
        });

        $('*[data-element="beauty_pro_upload_documents"]').on('change', function (e) {
            if (e.target.checked) {
                // show
                $('*[data-target="beauty_pro_upload_documents_div"]').show();
            } else {
                // hide
                $('*[data-target="beauty_pro_upload_documents_div"]').hide();
            }
        });

        $('*[data-element="beauty_pro_history_go_to"]').on('click', function () {
            var page = $(this).attr('data-page');
            if (!page) {
                return false;
            }

            goTo('pagenum', page);
        });

        $('*[data-element="beauty_pro_login"]').click(function (event) {
            event.stopPropagation();
            var form = event.target.form;
            $('.modal').addClass('overlay preloader')
            $.ajax({
                dataType: "json",
                cache: false,
                url: $(form).attr('action'),
                type: form.method,
                data: $(form).serialize(),
                success: function (data) {
                    if (data.success) {
                        var defaultAction = true;

                        if (modalParams && Object.keys(modalParams).length) {
                            if (modalParams.hasOwnProperty('callback')) {
                                defaultAction = false;

                                window[modalParams.callback]($(form).serializeArray(), data);
                            }
                        }

                        if (defaultAction) {
                            location.reload();
                        }
                    } else {
                        if (data.errors.hasOwnProperty('user') && '1006' == data.errors.code) {
                            var localUser = data.errors.user;
                            // set form
                            $('*[data-element="beauty_pro_update_popup_div"] form [name="role"]').val(localUser['role']);
                            if ('salon' == localUser['role']) {
                                $('*[data-element="beauty_pro_update_popup_div"] form [name="firstname"]').val('');
                                $('*[data-element="beauty_pro_update_popup_div"] form [name="firstname"]')[0].style.display = 'none';//.addClass('display-none');
                            } else {
                                $('*[data-element="beauty_pro_update_popup_div"] form [name="firstname"]').val(localUser['profile']['firstname'])
                                $('*[data-element="beauty_pro_update_popup_div"] form [name="firstname"]')[0].style.display = 'block';//.removeClass('display-none');
                            }

                            $('*[data-element="beauty_pro_update_popup_div"] form [name="lastname"]').val(localUser['profile']['lastname'])
                            $('*[data-element="beauty_pro_update_popup_div"] form [name="email"]').val(localUser['email'])
                            //$('*[data-element="beauty_pro_update_popup_div"] form [name="state"]').val(data.errors.user['licenses'][0]['state'])
                            $('*[data-element="beauty_pro_update_popup_div"] form [name="shortstate"]').val(localUser['licenses'][0]['state'])

                            //$('*[data-element="beauty_pro_update_popup_div"] form [name="license"]').val(data.errors.user['licenses'][0]['licenseno'])

                            var list = $('*[data-element="beauty_pro_update_state"]');
                            var arr = [], el = $(list[0]);
                            try {
                                arr = JSON.parse(el.attr('data-source'));
                            } catch (e) { }

                            if (arr) {
                                var state = localUser['licenses'][0]['state'];
                                if (arr.hasOwnProperty(state)) {
                                    $('*[data-element="beauty_pro_update_popup_div"] form [name="state"]').val(arr[state])
                                }
                            }

                            var nick = localUser['profile']['nickname'];
                            if (!nick) {
                                if (localUser['profile']['firstname']) {
                                    nick = localUser['profile']['firstname'] + ' ' + localUser['profile']['lastname'];
                                } else {
                                    nick = localUser['profile']['lastname'];
                                }
                            }

                            var text = '<p>Dear ' + nick + ', unfortunately, our records show that your license has expired.</p>' +
                                '<p>Please go through our quick verification process in order to update your license credentials. ' +
                                'Thank you for your commitment to the professional beauty industry!</p>';

                            $('*[data-element="beauty_pro_update_popup_div"] .blist__form__header').html(text);

                            setActive('update')
                        } else {

                            $(form).addClass('error');
                            $('*[data-element="beauty_pro_login_error"]').text(data.errors.message);
                            setLoginErrors({ 'email': 'err', 'password': 'err' });
                        }
                    }

                    $('.modal').removeClass('overlay preloader')
                },
                error: function (err) {
                    console.log(err)
                    $('.modal').removeClass('overlay preloader')
                }
            });
        });

        $('*[data-element="beauty_pro_forgot"]').click(function (event) {
            event.stopPropagation();
            var form = event.target.form;
            $('.modal').addClass('overlay preloader')
            $.ajax({
                cache: false,
                dataType: "json",
                url: $(form).attr('action'),
                type: form.method,
                data: $(form).serialize(),
                success: function (data) {
                    var hint = $('*[data-element="beauty_pro_forgot_error"]');
                    if (data.success) {
                        hint.removeClass('error');
                        hint.addClass('success');
                        hint.text(data.data.message);
                    } else {
                        hint.addClass('error');
                        hint.text(data.errors.message);
                    }
                    $('.modal').removeClass('overlay preloader')
                },
                error: function (err) {
                    console.log(err)
                    $('.modal').removeClass('overlay preloader')
                }
            });
        });

        $('*[data-element="beauty_pro_support"]').click(function (event) {
            event.stopPropagation();

            if (!validateCaptcha('support')) {
                $('*[data-element="beauty_pro_support_error"]').text('Please verify that you\'re not a robot.');
                return;
            } else {
                $('*[data-element="beauty_pro_support_error"]').text('');
            }

            var form = event.target.form;
            $('.modal').addClass('overlay preloader')

            $('*[data-element="beauty_pro_support_popup_div"] *[data-element="beauty_pro_support_message"]').hide();

            $('*[data-element="beauty_pro_support_error"]').html('');
            $.ajax({
                cache: false,
                dataType: "json",
                url: $(form).attr('action'),
                type: form.method,
                data: $(form).serialize(),
                success: function (data) {
                    $('.modal').removeClass('overlay preloader')

                    if (data.success) {
                        $('*[data-element="beauty_pro_support_popup_div"] *[data-element="beauty_pro_support_message"]').show();
                    } else {
                        $('*[data-element="beauty_pro_support_error"]').html('<p>' + data.errors.message + '</p>');
                    }
                },
                error: function (err) {
                    console.log(err)
                    $('.modal').removeClass('overlay preloader')
                    //$('*[data-element="beauty_pro_support_popup_div"] form').hide();
                    $('*[data-element="beauty_pro_support_popup_div"] *[data-element="beauty_pro_support_message"]').show();
                }
            });
        });

        $('*[data-element="beauty_pro_international"]').click(function (event) {
            event.stopPropagation();

            if (!validateCaptcha('international')) {
                $('*[data-element="beauty_pro_international_error"]').text('Please verify that you\'re not a robot.');
                return;
            } else {
                $('*[data-element="beauty_pro_international_error"]').text('');
            }

            var form = event.target.form;
            $('.modal').addClass('overlay preloader')

            $('*[data-element="beauty_pro_international_popup_div"] *[data-element="beauty_pro_international_message"]').hide();

            $('*[data-element="beauty_pro_international_error"]').html('');
            $.ajax({
                cache: false,
                dataType: "json",
                url: $(form).attr('action'),
                type: form.method,
                data: $(form).serialize(),
                success: function (data) {
                    $('.modal').removeClass('overlay preloader')

                    if (data.success) {
                        $('*[data-element="beauty_pro_international_popup_div"] *[data-element="beauty_pro_international_message"]').show();
                    } else {
                        $('*[data-element="beauty_pro_international_error"]').html('<p>' + data.errors.message + '</p>');
                    }
                },
                error: function (err) {
                    console.log(err)
                    $('.modal').removeClass('overlay preloader')
                    //$('*[data-element="beauty_pro_support_popup_div"] form').hide();
                    $('*[data-element="beauty_pro_international_popup_div"] *[data-element="beauty_pro_international_message"]').show();
                }
            });
        });

        $('*[data-element="beauty_pro_link"]').click(function () {
            var link = $(this).attr('data-href');
            if (link) window.location = link;
        });

        $('*[data-element="beauty_pro_logout"]').click(function (event) {
            event.stopPropagation();
            $('#beauty_pro_logout_popup').modal('show');
        })

        $('*[data-element="beauty_pro_logout_action"]').click(function (event) {
            $('.modal').addClass('overlay preloader');
            event.preventDefault();
            var form = event.target.form;
            $.ajax({
                cache: false,
                dataType: "json",
                url: $(form).attr('action'),
                type: form.method,
                data: $(form).serialize(),
                success: function (data) {
                    if (data.success) {
                        location.reload();
                    }
                    $('.modal').removeClass('overlay preloader')
                },
                error: function (err) {
                    console.log(err)
                    $('.modal').removeClass('overlay preloader')
                }
            });
        });

        $('*[data-element="beauty_pro_logout_close"]').click(function (e) {
            $('.modal').addClass('overlay preloader');
            e.preventDefault();

            $('*[data-element="beauty_pro_support_popup_div"]').hide()
        })

        $('*[data-element="beauty_pro_save_key"]').click(function (event) {
            $('.modal').addClass('overlay preloader')
            $.ajax({
                cache: false,
                dataType: 'json',
                url: $(this).attr('data-href'),
                type: 'POST',
                data: { action: 'beauty_pro_save_key', key: $('*[data-element="apikey').val() },
                success: function (data) {
                    if (data.success) {
                        $('*[data-element="beauty_pro_save_key_message"] div').html(data.message);
                        location.reload();
                    } else {
                        if (data.hasOwnProperty('message')) {
                            $('*[data-element="beauty_pro_save_key_message"] div').html(data.message);
                        }
                    }
                    $('.modal').removeClass('overlay preloader')
                },
                error: function (err) {
                    console.log(err)
                    $('.modal').removeClass('overlay preloader')
                }
            });
        });

        $('*[data-element="beauty_pro_save_google_key"]').click(function (event) {
            $('.modal').addClass('overlay preloader')

            var params = {
                action: 'beauty_pro_save_google_key',
                key: $('*[data-element="beauty_pro_google_key').val(),
                secret: $('*[data-element="beauty_pro_google_secret').val()
            }

            $.ajax({
                cache: false,
                dataType: 'json',
                url: $(this).attr('data-href'),
                type: 'POST',
                data: params,
                success: function (data) {
                    if (data.success) {
                        $('*[data-element="beauty_pro_save_google_key_message"] div').html(data.message);
                        location.reload();
                    } else {
                        if (data.hasOwnProperty('message')) {
                            $('*[data-element="beauty_pro_save_google_key_message"] div').html(data.message);
                        }
                    }
                    $('.modal').removeClass('overlay preloader')
                },
                error: function (err) {
                    console.log(err)
                    $('.modal').removeClass('overlay preloader')
                }
            });
        });

        $('*[data-element="beauty_pro_button_copy"]').click(function (e) {
            var $temp = $("<input>"),
                text = $('*[data-element="beauty_pro_button_result"]').val();

            $("body").append($temp);
            $temp.val(text).select();
            document.execCommand("copy");
            $temp.remove();
        })

        $('*[data-element="beauty_pro_button_reset"]').click(function (e) {
            resetButton()
        })

        $('*[data-element="beauty_pro_button_text"]').on('input', function () {
            updateShortcode();
        })
        $('*[data-element="beauty_pro_button_size"]').change(function () {
            updateShortcode();
        })
        $('*[data-element="beauty_pro_button_alignment"]').change(function () {
            updateShortcode();
        })
        $('*[data-element="beauty_pro_button_text_color"]').on('input', function () {
            updateShortcode();
        })
        $('*[data-element="beauty_pro_button_background_color"]').on('input', function () {
            updateShortcode();
        })
        $('*[data-element="beauty_pro_button_result"]').change(function () {
            updateShortcode();
        })

        $('*[data-element="beauty_pro_remove_tag"]').click(function (event) {
            $('.modal').addClass('overlay preloader')
            $.ajax({
                cache: false,
                url: $(this).attr('data-href'),
                type: 'POST',
                dataType: 'json',
                data: { action: 'beauty_pro_remove_tag', id: $(this).attr('data-id') },
                success: function (data) {
                    if (data.success) {
                        location.reload();
                    }
                    $('.modal').removeClass('overlay preloader')
                },
                error: function (err) {
                    console.log(err)
                    $('.modal').removeClass('overlay preloader')
                }
            });
        });

        var createPagination = function (data) {
            $('#pagination-container').pagination({
                dataSource: data,
                pageSize: 10,
                autoHidePrevious: true,
                autoHideNext: true,
                callback: function (data, pagination) {
                    // template method of yourself
                    var html = simpleTemplating(data);
                    $('#data-container').html(html);

                    $('*[data-element="beauty_pro_add_tag_button"]').on("click", function () {
                        var id = $(this).attr('id');//$('*[data-element="beauty_pro_add_list"]');
                        if (id) {
                            $('.modal').addClass('overlay preloader')
                            $.ajax({
                                cache: false,
                                url: $('#pagination-container').attr('data-url'),
                                type: 'POST',
                                dataType: 'json',
                                data: { action: 'beauty_pro_add_tag', id: id },
                                success: function (data) {
                                    if (data.success) {
                                        location.reload();
                                    }
                                    $('.modal').removeClass('overlay preloader')
                                },
                                error: function (err) {
                                    console.log(err)
                                    $('.modal').removeClass('overlay preloader')
                                }
                            });
                        }
                    })
                }
            });
        }

        if ($('#pagination-container').length) {
            createPagination(JSON.parse($('#pagination-container').attr('data-source')));
        }

        function simpleTemplating(data) {
            var html = '';
            if (data.length) {
                $('*[data-element="beauty_pro_empty_search"]').addClass('display-none');
                html += '<ul class="list-group list-group-flush">';
                $.each(data, function (index, item) {
                    html += '<li class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">';
                    if (item.hasOwnProperty('image')) {
                        html += '<img class="item-img" src="' + item['image'] + '">';
                    }
                    html += '<h2 class="title">' + item['name'] + '</h2>' +
                        '<a id="' + item['id'] + '" data-id="' + item['name'] + '" data-element="beauty_pro_add_tag_button" href="#" class="btn btn-outline-secondary btn-sm" tabindex="-1" role="button">Add</a>' +
                        '</li>';
                });
                html += '</ul>';
            } else {
                $('*[data-element="beauty_pro_empty_search"]').removeClass('display-none');
            }
            return html;
        }

        $('*[data-element="beauty_pro_go_to"]').click(function (event) {
            event.stopPropagation();
            var page = $(this).attr('data-page');
            if (!page) {
                return false;
            }

            goTo('pagenum', page);
        });

        // load autocomplete - state
        var list = $('*[data-element="beauty_pro_verification_state"]');
        if (list.length) {
            var arr = [], configArr = [], el, form;
            for (let oneElement of list) {
                el = $(oneElement);
                try {
                    arr = JSON.parse(el.attr('data-source'));
                } catch (e) { }

                var ret = {};
                for (var key in arr) {
                    ret[arr[key]] = key;
                }

                el.autocomplete({
                    delay: 0,
                    source: Object.values(arr),
                    change: function (event, ui) {
                        el.val((ui.item ? ui.item.value : ""));
                    },
                    select: function (event, ui) {
                        form = $(this).parent().parent();
                        var config = form.find('*[data-element="beauty_pro_verification_config"]');
                        try {
                            configArr = JSON.parse($(config[0]).attr('data-source'));
                        } catch (e) { }

                        var hint = form.find('*[data-element="beauty_pro_verification_license_hint"]');
                        hint = $(hint);

                        var hintState = form.find('*[data-element="beauty_pro_verification_hint_state"]');
                        hintState = $(hintState);

                        var stateEl = form.find('*[data-element="beauty_pro_verification_short_state"]');
                        //stateEl = $(stateEl[0]);

                        form.find('*[data-element="beauty_pro_verification_short_state"]').val('');

                        hintState.text('');
                        hintState.hide();
                        if (ret[ui.item.label]) {
                            var imageElement = form.find('*[data-element="beauty_pro_verification_license_image"]');
                            var salonImageElement = form.find('*[data-element="beauty_pro_salon_verification_license_image"]');
                            let excludedArr = BLConfig['manual-states']; // ['ar', 'ms', 'ok'];
                            var shortState = ret[ui.item.label];
                            if (excludedArr.indexOf(shortState.toUpperCase()) != -1) {
                                // display image upload
                                imageElement.removeClass('display-none');
                                salonImageElement.removeClass('display-none');
                            } else {
                                // hide image upload
                                imageElement.addClass('display-none');
                                salonImageElement.addClass('display-none');
                                var file = form.find('*[data-element="beauty_pro_verification_license_image"] #image').get(0);
                                if (file) $(file).val('');
                            }

                            form.find('*[data-element="beauty_pro_verification_short_state"]').val(shortState);
                            if (configArr[shortState] && configArr[shortState] !== undefined) {
                                hintState.show();
                                hintState.text(configArr[shortState]);
                            } else {
                                hintState.text('');
                                hintState.hide();
                            }
                        }
                    },
                }).autocomplete("widget").addClass("highlight");
            }
        }

        // load autocomplete - state
        var list = $('*[data-element="beauty_pro_update_state"]');
        if (list.length) {
            var arr = [], configArr = [];
            var el = $(list[0]);
            try {
                arr = JSON.parse(el.attr('data-source'));
            } catch (e) { }

            var ret = {};
            for (var key in arr) {
                ret[arr[key]] = key;
            }

            var config = $('*[data-element="beauty_pro_update_config"]');
            try {
                configArr = JSON.parse($(config[0]).attr('data-source'));
            } catch (e) { }

            var hint = $('*[data-element="beauty_pro_update_license_hint"]');
            hint = $(hint[0]);

            $('*[data-element="beauty_pro_update_short_state"]').val('');
            el.autocomplete({
                /*classes: {
                    "ui-autocomplete": "highlight"
                },*/
                delay: 0,
                source: Object.values(arr),
                change: function (event, ui) {
                    el.val((ui.item ? ui.item.value : ""));
                },
                select: function (event, ui) {
                    hint.text('');
                    if (ret[ui.item.label]) {
                        var imageElement = $('*[data-element="beauty_pro_update_license_image"]');
                        let excludedArr = BLConfig['manual-states']; // ['ar', 'ms', 'ok'];
                        var shortState = ret[ui.item.label];
                        if (excludedArr.indexOf(shortState.toUpperCase()) != -1) {
                            // display image upload
                            imageElement.removeClass('display-none');
                        } else {
                            // hide image upload
                            imageElement.addClass('display-none');
                            var file = $('*[data-element="beauty_pro_update_license_image"] #image').get(0);
                            if (file.files) {
                                $(file).val('');
                            }
                        }

                        $('*[data-element="beauty_pro_update_short_state"]').val(shortState);
                        if (configArr[shortState] && configArr[shortState] !== undefined) {
                            hint.text(configArr[shortState]);
                        } else {
                            hint.text('');
                        }
                    }
                },
            }).autocomplete("widget").addClass("highlight");
        }

        // search timer
        var typingTimerSearch; //timer identifier
        var doneTypingInterval = 2000;  //time in ms, 2 second for example
        var $inputSearch = $('*[data-element="beauty_pro_add_list"]');

        //on keyup, start the countdown
        $inputSearch.on('keyup', function (e) {
            if (e.keyCode === 13) {
                return searchProduct();
            }
            clearTimeout(typingTimerSearch);
            typingTimerSearch = setTimeout(searchProduct, doneTypingInterval);
        });

        //on keydown, clear the countdown
        $inputSearch.on('keydown', function () {
            clearTimeout(typingTimerSearch);
        });

        var searchProduct = function () {
            $('#pagination-container').pagination('destroy');
            var data = JSON.parse($('#pagination-container').attr('data-source'));
            var search = $('*[data-element="beauty_pro_add_list"]').val();
            var result = [];
            for (var i = 0, len = data.length; i < len; i++) {
                if (search) {
                    if (data[i]['name'].search(new RegExp(search, "i")) > 0) {
                        result.push(data[i]);
                    }
                } else {
                    result.push(data[i]);
                }
            }
            createPagination(result);
        }
        // search timer
        var typingTimer;                //timer identifier
        var doneTypingInterval = 2000;  //time in ms, 2 second for example
        var $input = $('*[data-element="beauty_pro_search_product"]');

        //on keyup, start the countdown
        $input.on('keyup', function (e) {
            if (e.keyCode === 13) {
                return goTo('search', $input.val());
            }
            clearTimeout(typingTimer);
            typingTimer = setTimeout(goToSearch, doneTypingInterval);
        });

        //on keydown, clear the countdown
        $input.on('keydown', function () {
            clearTimeout(typingTimer);
        });

        var goToSearch = function () {
            return goTo('search', $input.val());
        }

        var goTo = function (key, value) {
            var regex = /[?&]([^=#]+)=([^&#]*)/g, url = window.location.href, params = {}, match;
            while (match = regex.exec(url)) {
                params[match[1]] = match[2];
            }
            params[key] = value;

            if (-1 !== ['limit', 'period', 'status'].indexOf(key)) {
                params['pagenum'] = 1;
            }

            var h = '';
            for (var k in params) {
                h += k + '=' + params[k] + '&';
            }

            location.href = '?' + h;
        };
        // end timer

        var setVerificationErrors = function (errors) {
            var form = $('*[data-element="beauty_pro_signup_popup_div"] div#individual input');
            for (var formEl of form) {
                var name = formEl.getAttribute('name');
                if (!name) {
                    name = formEl.getAttribute('id');
                }
                if (-1 !== ['firstname', 'lastname', 'email', 'license', 'image', 'state'].indexOf(name)) {
                    var hint = $('div#individual *[data-element="beauty_pro_verification_error_' + name + '"]').get(0);
                    if (undefined !== errors[name]) {
                        $(formEl).addClass('error');
                        if (hint) {
                            $(hint).removeClass('display-none');
                            $(hint).text(errors[name]);
                        }
                    } else {
                        $(formEl).removeClass('error');
                        $(hint).addClass('display-none');
                    }
                }
            }
        }

        var setSalonVerificationErrors = function (errors) {
            var form = $('*[data-element="beauty_pro_signup_popup_div"] div#salon input');
            for (var formEl of form) {
                var name = formEl.getAttribute('name');
                if (!name) {
                    name = formEl.getAttribute('id');
                }
                if (-1 !== ['businessname', 'email', 'license', 'image', 'state'].indexOf(name)) {
                    var hint = $('div#salon *[data-element="beauty_pro_salon_verification_error_' + name + '"]').get(0);
                    if (undefined !== errors[name]) {
                        $(formEl).addClass('error');
                        if (hint) {
                            $(hint).removeClass('display-none');
                            $(hint).text(errors[name]);
                        }
                    } else {
                        $(formEl).removeClass('error');
                        $(hint).addClass('display-none');
                    }
                }
            }
        }

        var setVerificationErrorsStep2 = function (errors) {
            var selector = '*[data-element="beauty_pro_signup_step_1_1_popup_div"]';
            var form = $(selector + ' input, ' + selector + ' select');
            for (var formEl of form) {
                var name = formEl.getAttribute('name');
                if (-1 !== ['beauty_pro_signup_step1_radio', 'speciality[]', 'confirm'].indexOf(name)) {
                    var hint = $('*[data-element="beauty_pro_signup_error_' + name + '"]').get(0);
                    if (undefined !== errors[name]) {
                        $(formEl).addClass('error');
                        if ($(formEl).attr('type') == 'checkbox') {
                            $(formEl).closest(".form-group").find(".form-control").addClass('error');
                        }
                        if (hint) {
                            $(hint).removeClass('display-none');
                            $(hint).text(errors[name]);
                        }
                    } else {
                        $(formEl).removeClass('error');
                        $(formEl).closest(".form-group").find(".form-control").removeClass('error');
                        $(hint).addClass('display-none');
                    }
                }
            }
        }

        var setLoginErrors = function (errors) {
            var form = $('*[data-element="beauty_pro_login_div"] input');
            for (var formEl of form) {
                var name = formEl.getAttribute('name');
                if (undefined !== errors[name]) {
                    $(formEl).addClass('error');
                } else {
                    $(formEl).removeClass('error');
                }
            }
        }

        var escapeHtml = function (str) {
            var map =
            {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return str.replace(/[&<>"']/g, function (m) { return map[m]; });
        }
    })

    var setActive = function (step = '') {
        var actions = {
            login: $('*[data-element="beauty_pro_login_div"]'),
            forgot: $('*[data-element="beauty_pro_forgot_div"]'),

            signup: $('*[data-element="beauty_pro_signup_popup_div"]'),
            signupStep1: $('*[data-element="beauty_pro_signup_step_1_1_popup_div"]'),
            signupStep2: $('*[data-element="beauty_pro_signup_step2_popup_div"]'),

            signupStep2Notify: $('*[data-element="beauty_pro_update_step2_notify_popup_div"]'),
            messageNotify: $('*[data-element="beauty_pro_notify_popup_div"]'),

            intro: $('*[data-element="beauty_pro_intro_div"]'),

            update: $('*[data-element="beauty_pro_update_popup_div"]'),
            updateStep1: $('*[data-element="beauty_pro_update_step_1_1_popup_div"]'),
            updateStep2: $('*[data-element="beauty_pro_update_step2_popup_div"]'),

            support: $('*[data-element="beauty_pro_support_popup_div"]'),
            international: $('*[data-element="beauty_pro_international_popup_div"]'),

            supportHelp: $('*[data-element="beauty_pro_support_message"]')
        }

        for (let action of Object.keys(actions)) {
            actions[action][0].style.display = 'none'
        }

        // hide error messages
        $('.beauty_pro_text_error').text('');

        if (step) {
            actions[step][0].style.display = 'block';
        }
    }

    // update license
    $('*[data-element="beauty_pro_update_error_speciality_popup"]').click(function (event) {
        event.stopPropagation();
        var popup = $('*[data-element="beauty_pro_update_error_speciality_div"]');
        if ('block' == popup[0].style.display) {
            popup[0].style.display = 'none';
            $(this).removeClass('active');
        } else {
            popup[0].style.display = 'block';
            $(this).addClass('active');
        }
    });

    $('*[data-element="beauty_pro_update_step2_speciality"]').click(function () {
        $('*[data-element="beauty_pro_update_step2_speciality"]').not(this).prop('checked', false);
        var val = $(this).val();
        var ischecked = $(this).is(':checked');
        var tables = $('*[data-element="beauty_pro_update_step2_speciality_table"]');
        for (var table of tables) {
            if ($(table).attr('data-val') == val) {
                if (ischecked) {
                    table.style.display = 'block';
                } else {
                    table.style.display = 'none';
                }
            } else {
                table.style.display = 'none';
            }
        }
    });

    var but = $('*[data-element="beauty_pro_update_error_speciality_div"] *[data-element="beauty_pro_update_step2_speciality"]');
    if (but.length) {
        but[0].click();
    }

    var addCaptcha = function (type) {
        if (typeof grecaptcha !== 'undefined') {
            if (BLConfig.hasOwnProperty('captcha-key') && BLConfig['captcha-key']) {
                if (0 == document.getElementById(type + "_captcha").innerHTML.length) {
                    grecaptcha.ready(function () {
                        var id = grecaptcha.render(type + "_captcha", { sitekey: BLConfig['captcha-key'] });
                        captchaIDs[type] = id;
                    });
                }
            }
        }
    }

    var validateCaptcha = function (type) {
        var result = true;
        if (typeof grecaptcha !== 'undefined') {
            if (BLConfig.hasOwnProperty('captcha-key') && BLConfig['captcha-key']) {
                try {
                    var response = grecaptcha.getResponse(captchaIDs[type]);
                    if (response.length == 0) result = false;
                } catch (e) { console.log(e) }
            }
        }

        return result;
    }

    var setUpdateErrors = function (errors) {
        var form = $('*[data-element="beauty_pro_update_popup_div"] input');
        for (var formEl of form) {
            var name = formEl.getAttribute('name');
            if (!name) {
                name = formEl.getAttribute('id');
            }
            if (-1 !== ['firstname', 'lastname', 'email', 'state', 'license', 'image'].indexOf(name)) {
                var hint = $('*[data-element="beauty_pro_verification_error_' + name + '"]').get(0);
                if (undefined !== errors[name]) {
                    $(formEl).addClass('error');
                    if (hint) {
                        $(hint).removeClass('display-none');
                        $(hint).text(errors[name]);
                    }
                } else {
                    $(formEl).removeClass('error');
                    $(hint).addClass('display-none');
                }
            }
        }
    }
    // end update

    var getFormData = function ($form) {
        console.log($form)
        var unindexed_array = $form.serializeArray();
        var indexed_array = {};

        $.map(unindexed_array, function (n, i) {
            indexed_array[n['name']] = n['value'];
        });

        return indexed_array;
    }

    var updateShortcode = function () {
        var prefix = 'beauty_pro_button_',
            options = [],
            str = 'beauty-license-verification-button',
            preview = $('*[data-element="' + prefix + 'preview"]'),
            result = $('*[data-element="' + prefix + 'result"]'),
            text = $('*[data-element="' + prefix + 'text"]'),
            size = $('*[data-element="' + prefix + 'size"]'),
            alignment = $('*[data-element="' + prefix + 'alignment"]'),
            textColor = $('*[data-element="' + prefix + 'text_color"]'),
            backgroundColor = $('*[data-element="' + prefix + 'background_color"]');

        var res = `
            <div style="text-align: `+ alignment.val() + `;">
                <button type="button" class="com--beauty-pro"><span data-node="label">`+ text.val() + `</span></button>
                <style type="text/css">
                    .com--beauty-pro { 
                        border-radius: 2px;
                         background-color: `+ backgroundColor.val() + `; 
                         color: `+ textColor.val() + `; 
                         font-size: ` + size.val() + `px;
                    } 
                    .com--beauty-pro .com--logout { border-bottom: 1px solid `+ textColor.val() + ` }
                </style>
            </div>`;

        preview.html(res);

        options.push('alignment="' + alignment.val() + '"');
        options.push('text="' + text.val() + '"');
        options.push('background-color="' + backgroundColor.val() + '"');
        options.push('color="' + textColor.val() + '"');
        options.push('font-size="' + size.val() + 'px"')

        result.val('[' + str + ' ' + options.join(' ') + ']');
    }

    var resetButton = function () {
        var prefix = 'beauty_pro_button_';
        var preview = $('*[data-element="' + prefix + 'preview"]'),
            result = $('*[data-element="' + prefix + 'result"]'),
            text = $('*[data-element="' + prefix + 'text"]'),
            size = $('*[data-element="' + prefix + 'size"]'),
            alignment = $('*[data-element="' + prefix + 'alignment"]'),
            textColor = $('*[data-element="' + prefix + 'text_color"]'),
            backgroundColor = $('*[data-element="' + prefix + 'background_color"]');

        text.val('BEAUTY PRO');
        size.val(14);
        alignment.val('center');
        //textColor.val('#ffffff');
        textColor.parents('.wp-picker-input-wrap').find('.wp-picker-default').click()

        //backgroundColor.val('#ff0000');
        backgroundColor.parents('.wp-picker-input-wrap').find('.wp-picker-default').click()

        var res = `
            <div style="text-align: `+ alignment.val() + `;">
                <button type="button" class="com--beauty-pro"><span data-node="label">`+ text.val() + `</span></button>
                <style type="text/css">
                    .com--beauty-pro { 
                        border-radius: 2px;
                         background-color: `+ backgroundColor.val() + `; 
                         color: `+ textColor.val() + `; 
                         font-size: ` + size.val() + `px;
                    } 
                    .com--beauty-pro .com--logout { border-bottom: 1px solid `+ textColor.val() + ` }
                </style>
            </div>`;

        preview.html(res);

        result.val('[beauty-license-verification-button]');
    }
})(jQuery);
