<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;


class DatabaseResultMock {
    private $rows;
    private $index = 0;

    public function __construct(array $rows) {
        $this->rows = $rows;
    }

    public function fetch() {
        if ($this->index < count($this->rows)) {
            return $this->rows[$this->index++];
        }
        return false;
    }
}

class DatabaseMock {
    private $returnValues = [];

    public function setReturnValue($method, $value) {
        $this->returnValues[$method] = $value;
    }

    public function select($query, $params = []) {
        $rows = $this->returnValues['select'] ?? [];
        return new DatabaseResultMock($rows);
    }

    public function insert($query, $params = []) {
        return $this->returnValues['insert'] ?? null;
    }

    public function update($query, $params = []) {
        return $this->returnValues['update'] ?? true;
    }

    public function delete($query, $params = []) {
        return $this->returnValues['delete'] ?? true;
    }

    public function execute($query, $params = []) {
        return $this->returnValues['execute'] ?? true;
    }
}

class BaseTest extends TestCase {
    protected function setUp(): void {
    }

    protected function tearDown(): void {
    }
}