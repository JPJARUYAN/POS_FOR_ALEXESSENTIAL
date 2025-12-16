<?php

require_once __DIR__ . '/../_init.php';

class User
{
    public $id;
    public $name;
    public $email;
    public $role;
    public $password;


    public function getHomePage()
    {
        if ($this->role === ROLE_ADMIN) {
            return 'admin_home.php';
        }
        return 'index.php';
    }

    // cache authenticated users by role (e.g. ['ADMIN' => User, 'CASHIER' => User])
    private static $currentUser = [];

    public function __construct($user)
    {
        $this->id = intval($user['id']);
        $this->name = $user['name'];
        $this->email = $user['email'];
        $this->role = $user['role'];
        $this->password = $user['password'];
    }

    // If $role is provided (ROLE_ADMIN or ROLE_CASHIER), return that role's
    // authenticated user. If $role is null, return any authenticated user
    // preferring ADMIN when both are logged in.
    public static function getAuthenticatedUser($role = null)
    {
        if ($role) {
            $key = 'user_id_' . strtolower($role);
            if (!isset($_SESSION[$key]))
                return null;

            if (!isset(static::$currentUser[$role])) {
                static::$currentUser[$role] = static::find($_SESSION[$key]);
            }

            return static::$currentUser[$role];
        }

        // No role requested, prefer ADMIN then CASHIER
        if (isset($_SESSION['user_id_admin'])) {
            return static::getAuthenticatedUser(ROLE_ADMIN);
        }

        if (isset($_SESSION['user_id_cashier'])) {
            return static::getAuthenticatedUser(ROLE_CASHIER);
        }

        return null;
    }

    public static function find($user_id)
    {
        global $connection;

        $stmt = $connection->prepare("SELECT * FROM `users` WHERE id=:id");
        $stmt->bindParam("id", $user_id);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $result = $stmt->fetchAll();

        if (count($result) >= 1) {
            return new User($result[0]);
        }

        return null;
    }

    public static function findByEmail($email)
    {
        global $connection;

        $stmt = $connection->prepare("SELECT * FROM `users` WHERE email=:email");
        $stmt->bindParam("email", $email);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $result = $stmt->fetchAll();

        if (count($result) >= 1) {
            return new User($result[0]);
        }

        return null;
    }

    public static function login($email, $password)
    {
        if (empty($email))
            throw new Exception("The email is required");
        if (empty($password))
            throw new Exception("The password is required");

        $user = static::findByEmail($email);

        if ($user && $user->password == $password) {
            return $user;
        }

        throw new Exception('Wrong email or password.');
    }

    /**
     * Get all users, optionally filtered by role
     * @param string|null $role Filter by role (e.g., 'CASHIER', 'ADMIN')
     * @return array Array of User objects
     */
    public static function getAll($role = null)
    {
        global $connection;

        if ($role) {
            $stmt = $connection->prepare("SELECT * FROM users WHERE role = :role ORDER BY name ASC");
            $stmt->execute([':role' => $role]);
        } else {
            $stmt = $connection->prepare("SELECT * FROM users ORDER BY name ASC");
            $stmt->execute();
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $users = [];
        foreach ($rows as $row) {
            $users[] = new User($row);
        }

        return $users;
    }

    /**
     * Create a new user
     * @param string $name User's full name
     * @param string $email User's email address
     * @param string $password User's password
     * @param string $role User's role (default: CASHIER)
     * @return User The created user object
     * @throws Exception If validation fails or email already exists
     */
    public static function create($name, $email, $password, $role = 'CASHIER')
    {
        global $connection;

        // Validation
        if (empty($name))
            throw new Exception("Name is required");
        if (empty($email))
            throw new Exception("Email is required");
        if (empty($password))
            throw new Exception("Password is required");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check if email already exists
        $existingUser = static::findByEmail($email);
        if ($existingUser) {
            throw new Exception("Email already exists");
        }

        // Insert new user
        $stmt = $connection->prepare(
            "INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)"
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $password,
            ':role' => $role
        ]);

        $userId = $connection->lastInsertId();
        return static::find($userId);
    }

    /**
     * Update an existing user
     * @param int $userId User ID to update
     * @param string $name User's full name
     * @param string $email User's email address
     * @param string|null $password User's password (optional, only update if provided)
     * @return User The updated user object
     * @throws Exception If validation fails or user not found
     */
    public static function update($userId, $name, $email, $password = null)
    {
        global $connection;

        // Validation
        if (empty($name))
            throw new Exception("Name is required");
        if (empty($email))
            throw new Exception("Email is required");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check if user exists
        $user = static::find($userId);
        if (!$user) {
            throw new Exception("User not found");
        }

        // Check if email already exists for another user
        $existingUser = static::findByEmail($email);
        if ($existingUser && $existingUser->id != $userId) {
            throw new Exception("Email already exists");
        }

        // Update user
        if ($password && !empty($password)) {
            $stmt = $connection->prepare(
                "UPDATE users SET name = :name, email = :email, password = :password WHERE id = :id"
            );
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $password,
                ':id' => $userId
            ]);
        } else {
            $stmt = $connection->prepare(
                "UPDATE users SET name = :name, email = :email WHERE id = :id"
            );
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':id' => $userId
            ]);
        }

        return static::find($userId);
    }

    /**
     * Delete a user
     * @param int $userId User ID to delete
     * @return bool True if deleted successfully
     * @throws Exception If user not found or trying to delete an admin
     */
    public static function delete($userId)
    {
        global $connection;

        // Check if user exists
        $user = static::find($userId);
        if (!$user) {
            throw new Exception("User not found");
        }

        // Prevent deleting admin users
        if ($user->role === ROLE_ADMIN) {
            throw new Exception("Cannot delete admin users");
        }

        // Delete user
        $stmt = $connection->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);

        return true;
    }
}