<?php

/*
    Copyright 2014 Matt Senate <mattsenate@gmail.com>
    
    This file is part of the Seltzer CRM Project
    persona_auth.inc.php - Persona Auth module

    Seltzer is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.

    Seltzer is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Seltzer.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @return This module's revision number.  Each new release should increment
 * this number.
 */
function persona_auth_revision () {
    return 1;
}

/**
 * Require dependencies
 * + BrowserID verification library
 * + SeltzerCRM user module, especially for user_data(), user_login(),
 *   and command_logout()
 */
require_once('Auth/BrowserID.php');
require_once($crm_root . '/modules/user/user.inc.php');

/**
 * Initialize variables
 */
$email = NULL;

/**
 * Handle persona-based login request.
 *
 * @return the url to display when complete.
 */
function command_persona_auth_login () {
    global $esc_post;

    // Field authentication request, prepare to verify
    if (isset($_POST['assertion']) && isset($_POST['email'])) {
        $user_opts = array(
            'filter' => array(
                'email' => $_POST['email']
            )
        );
        $users = user_data($user_opts);
    }
    // Check for user
    if (sizeof($users) < 1) {
        error_register('No user found');
        error_register('Invalid email');
        $next = crm_url('login');
        return;
    }

    // Verify email with persona
    $user = $users[0];
    $verifier = new Auth_BrowserID();
    $result = $verifier->verifyAssertion($_POST['assertion']);
    $email = $result->email;

    if ($result->status === 'okay') {
        $valid = true;
    }
    else {
        $valid = false;
    }

    if ($valid) {
        user_login($user['cid']);
        $next = crm_url();
    } else {
        error_register('Error:' . $result->reason);
        $next = crm_url('login');
    }

    // Redirect to index
    return $next;
}

/**
 * @return login form structure.
*/
function persona_auth_login_form () {
    $form = array(
        'name' => 'persona_auth_login_form',
        'type' => 'form',
        'method' => 'post',
        'command' => 'persona_auth_login',
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Log in with Persona',
                'fields' => array(
                    array(
                        'type' => 'hidden',
                        'name' => 'assertion',
                        'value' => ''
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Email (your Persona ID)',
                        'name' => 'email',
                        'class' => 'focus'
                    ),
                    array(
                        'type' => 'submit',
                        'name' => 'submitted',
                        'value' => 'Log in'
                    )
                )
            )
        )
    );
    return $form;
}

/**
 * @return The themed html string for a login form.
*/
function theme_persona_auth_login_form () {
    return theme('form', crm_get_form('persona_auth_login'));
}

/**
 * Javscript for persona
 */

$persona_js = '<script src="https://login.persona.org/include.js"></script>';

$navigator_js = "<script>
navigator.id.watch({
onlogin: function (assertion) {
var assertion_field = document.getElementsByName(\"assertion\")[0];
assertion_field.value = assertion;
var login_form = document.getElementsByName(\"persona_auth_login_form\")[0];
//login_form.submit();
},
onlogout: function () {
//window.location = '?command=logout';
}
});
</script>";

$signin_js = "<script>
var signinLink = document.getElementsByName('submitted')[0];
if (signinLink) {
  signinLink.onclick = function() { navigator.id.request(); };
}</script>";

/**
 * Page hook.  Adds ser module content to a page before it is rendered.
 *
 * @param &$page_data Reference to data about the page being rendered.
 * @param $page_name The name of the page being rendered.
 * @param $options The array of options passed to theme('page').
*/
function persona_auth_page (&$page_data, $page_name, $options) {
    global $persona_js;
    global $navigator_js;
    global $signin_js;

    switch ($page_name) {

        case 'persona_auth_login':
            page_add_content_top($page_data, $signin_js);
            page_add_content_top($page_data, $navigator_js);
            page_add_content_top($page_data, $persona_js);
            page_add_content_top($page_data, theme('form', crm_get_form('persona_auth_login')));
            break;
    }
}

