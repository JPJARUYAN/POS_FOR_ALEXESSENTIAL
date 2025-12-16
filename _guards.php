<?php

require_once '_init.php';

class Guard {

    public static function adminOnly()
    {
        $currentUser = User::getAuthenticatedUser(ROLE_ADMIN);

        if (!$currentUser || $currentUser->role !== ROLE_ADMIN) {
            redirect('login.php');
        }
        // mark the current page context as admin so templates can render role-appropriate UI
        $GLOBALS['CURRENT_ROLE_CONTEXT'] = ROLE_ADMIN;
    }

    public static function cashierOnly()
    {
        $currentUser = User::getAuthenticatedUser(ROLE_CASHIER);

        if (!$currentUser || $currentUser->role !== ROLE_CASHIER) {
            redirect('login.php');
        }
        // mark the current page context as cashier so templates can render role-appropriate UI
        $GLOBALS['CURRENT_ROLE_CONTEXT'] = ROLE_CASHIER;
    }

    public static function hasModel($modelClass)
    {
        $model = $modelClass::find(get('id'));

        if ($model == null) {
            header('Content-type: text/plain');
            die('Page not found');
        }

        return $model;
    }

    public static function guestOnly() 
    {
        // If any user is logged in (check admin first, then cashier), redirect to their home.
        $adminUser = User::getAuthenticatedUser(ROLE_ADMIN);
        if ($adminUser && $adminUser->role === ROLE_ADMIN) {
            redirect($adminUser->getHomePage());
        }

        $cashierUser = User::getAuthenticatedUser(ROLE_CASHIER);
        if ($cashierUser && $cashierUser->role === ROLE_CASHIER) {
            redirect($cashierUser->getHomePage());
        }

        return;
    }
}