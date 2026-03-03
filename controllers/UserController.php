<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/auth_middleware.php';

class UserController {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    // create account (super_admin only)
    public function createEngineer($fullName, $email, $password) {
        require_role('super_admin');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        return $this->userModel->create($fullName, $email, $hash, 'engineer');
    }

    public function createForeman($fullName, $email, $password) {
        require_role('super_admin');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        return $this->userModel->create($fullName, $email, $hash, 'foreman');
    }

    public function createClient($fullName, $email, $password) {
        require_role('super_admin');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        return $this->userModel->create($fullName, $email, $hash, 'client');
    }

    // login logic could be moved here eventually
}
