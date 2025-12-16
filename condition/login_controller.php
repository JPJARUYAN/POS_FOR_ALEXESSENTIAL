<?php

require_once __DIR__.'/../_init.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') 
{
    $email = post('email');
    $password = post('password');
    $role = post('role');

    try {
        $user = User::login($email, $password);

        // If a role was selected, ensure it matches the user's role
        if (!empty($role) && $user->role !== $role) {
            throw new Exception('Wrong role for this account.');
        }

        // Store user id in a role-specific session slot so multiple roles
        // can be logged in concurrently (e.g. admin and cashier).
        $slot = 'user_id_'.strtolower($user->role);
        $_SESSION[$slot] = $user->id;

        redirect('../'.$user->getHomePage());
    } catch (Exception $error) {
        flashMessage('login', $error->getMessage(), FLASH_ERROR);
        redirect('../login.php');
    }
}
