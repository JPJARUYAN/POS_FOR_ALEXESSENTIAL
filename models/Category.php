<?php

require_once __DIR__.'/../_init.php';

class Category
{
    public $id;
    public $name;
    public $tax_rate;

    private static $cache = null;

    public function __construct($category)
    {
        $this->id = $category['id'];
        $this->name = $category['name'];
        $this->tax_rate = $category['tax_rate'] ?? 12.00;
    }

    public function update() 
    {
        global $connection;
        //Check if name is unique
        $category = self::findByName($this->name);
        if ($category && $category->id !== $this->id) throw new Exception('Name already exists.');

        $stmt = $connection->prepare('UPDATE categories SET name=:name, tax_rate=:tax_rate WHERE id=:id');
        $stmt->bindParam('name', $this->name);
        $stmt->bindParam('tax_rate', $this->tax_rate);
        $stmt->bindParam('id', $this->id);
        $stmt->execute();
        // Invalidate cache so UI shows updated categories immediately
        static::$cache = null;
    }

    public function delete() {
        global $connection;

        $stmt = $connection->prepare('DELETE FROM `categories` WHERE id=:id');
        $stmt->bindParam('id', $this->id);
        $stmt->execute();
        // Clear cached categories so UI reflects deletion
        static::$cache = null;
    }

    public static function all()
    {
        global $connection;

        if (static::$cache) return static::$cache;

        $stmt = $connection->prepare('SELECT * FROM `categories`');
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $result = $stmt->fetchAll();

        static::$cache = array_map(function($item){ return new Category($item); }, $result);

        return static::$cache;
    }

    public static function add($name)
    {
        global $connection;

        if (static::findByName($name)) throw new Exception('Name already exists');

        $stmt = $connection->prepare('INSERT INTO `categories`(name) VALUES (:name)');
        $stmt->bindParam("name", $name);
        $stmt->execute();
        // Clear cache so next call to all() includes the new category
        static::$cache = null;
    }

    public static function findByName($name)
    {
        global $connection;

        $stmt = $connection->prepare("SELECT * FROM `categories` WHERE name=:name");
        $stmt->bindParam("name", $name);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        
        $result = $stmt->fetchAll();

        if (count($result) >= 1) {
            return new Category($result[0]);
        }

        return null;
    }

    public static function find($id)
    {
        global $connection;

        $stmt = $connection->prepare("SELECT * FROM `categories` WHERE id=:id");
        $stmt->bindParam("id", $id);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        
        $result = $stmt->fetchAll();

        if (count($result) >= 1) {
            return new Category($result[0]);
        }

        return null;
    }
}