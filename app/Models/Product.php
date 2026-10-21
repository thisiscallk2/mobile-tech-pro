<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use RuntimeException;
use Throwable;

/**
 * Product model -- catalogue + coordinated stock writes.
 *
 * create()/update() write the products row and its inventory row inside a
 * single mysqli transaction so a product is never left without stock data.
 */
class Product extends Model
{
    /**
     * Create a product and its inventory row in one transaction.
     *
     * @param array{name:string,description:?string,category_id:?int,brand_id:?int,supplier_id:?int,cost_price:float,selling_price:float,image_path:?string,quantity:int,reorder_level:int,location:?string} $data
     * @return int new product_id, or 0 on failure
     */
    public function create(array $data): int
    {
        $sku = $this->generateSku($data['name']);

        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO products '
                . '(sku, name, description, category_id, brand_id, supplier_id, cost_price, selling_price, image_path, active) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $stmt->bind_param(
                'sssiiidds',
                $sku,
                $data['name'],
                $data['description'],
                $data['category_id'],
                $data['brand_id'],
                $data['supplier_id'],
                $data['cost_price'],
                $data['selling_price'],
                $data['image_path']
            );
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
            $productId = (int) $stmt->insert_id;
            $stmt->close();

            $inv = $this->db->prepare(
                'INSERT INTO inventory (product_id, quantity, reorder_level, location) VALUES (?, ?, ?, ?)'
            );
            $inv->bind_param('iiis', $productId, $data['quantity'], $data['reorder_level'], $data['location']);
            if (!$inv->execute()) {
                throw new RuntimeException($inv->error);
            }
            $inv->close();

            $this->db->commit();
            return $productId;
        } catch (Throwable $e) {
            $this->db->rollback();
            return 0;
        }
    }

    /**
     * Update a product and its inventory row in one transaction. When
     * $data['image_path'] is null the existing image is kept.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $this->db->begin_transaction();
        try {
            if (($data['image_path'] ?? null) !== null) {
                $stmt = $this->db->prepare(
                    'UPDATE products SET name = ?, description = ?, category_id = ?, brand_id = ?, '
                    . 'supplier_id = ?, selling_price = ?, image_path = ? WHERE product_id = ?'
                );
                $stmt->bind_param(
                    'ssiiidsi',
                    $data['name'],
                    $data['description'],
                    $data['category_id'],
                    $data['brand_id'],
                    $data['supplier_id'],
                    $data['selling_price'],
                    $data['image_path'],
                    $id
                );
            } else {
                $stmt = $this->db->prepare(
                    'UPDATE products SET name = ?, description = ?, category_id = ?, brand_id = ?, '
                    . 'supplier_id = ?, selling_price = ? WHERE product_id = ?'
                );
                $stmt->bind_param(
                    'ssiiidi',
                    $data['name'],
                    $data['description'],
                    $data['category_id'],
                    $data['brand_id'],
                    $data['supplier_id'],
                    $data['selling_price'],
                    $id
                );
            }
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
            $stmt->close();

            $inv = $this->db->prepare(
                'UPDATE inventory SET quantity = ?, reorder_level = ? WHERE product_id = ?'
            );
            $inv->bind_param('iii', $data['quantity'], $data['reorder_level'], $id);
            if (!$inv->execute()) {
                throw new RuntimeException($inv->error);
            }
            $inv->close();

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            return false;
        }
    }

    public function updatePricing(int $id, float $costPrice, float $sellingPrice): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE products SET cost_price = ?, selling_price = ? WHERE product_id = ?'
        );
        $stmt->bind_param('ddi', $costPrice, $sellingPrice, $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function deleteById(int $id): void
    {
        // inventory rows cascade via FK (ON DELETE CASCADE).
        $stmt = $this->db->prepare('DELETE FROM products WHERE product_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare($this->selectColumns() . ' WHERE p.product_id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allFiltered(?string $q, ?int $categoryId, int $limit, int $offset): array
    {
        [$where, $types, $params] = $this->buildFilter($q, $categoryId);

        $sql = $this->selectColumns() . ' ' . $where
             . ' ORDER BY p.created_at DESC, p.product_id DESC LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    public function countFiltered(?string $q, ?int $categoryId): int
    {
        [$where, $types, $params] = $this->buildFilter($q, $categoryId);

        $sql = 'SELECT COUNT(*) AS c FROM products p ' . $where;
        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array{total:int, in_stock:int, low_stock:int}
     */
    public function summaryCounts(): array
    {
        $sql = 'SELECT '
             . 'COUNT(*) AS total, '
             . 'SUM(i.quantity > i.reorder_level) AS in_stock, '
             . 'SUM(i.quantity <= i.reorder_level) AS low_stock '
             . 'FROM products p LEFT JOIN inventory i ON i.product_id = p.product_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'in_stock' => (int) ($row['in_stock'] ?? 0),
            'low_stock' => (int) ($row['low_stock'] ?? 0),
        ];
    }

    /**
     * Names of products at or below their reorder level (for the banner).
     *
     * @return array<int, string>
     */
    public function lowStockNames(): array
    {
        $sql = 'SELECT p.name FROM products p '
             . 'JOIN inventory i ON i.product_id = p.product_id '
             . 'WHERE i.quantity <= i.reorder_level ORDER BY i.quantity ASC, p.name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        $names = [];
        while ($row = $result->fetch_assoc()) {
            $names[] = (string) $row['name'];
        }
        $stmt->close();

        return $names;
    }

    /* ---------------------------------------------------------------- */

    private function selectColumns(): string
    {
        return 'SELECT p.product_id, p.sku, p.name, p.description, p.category_id, p.brand_id, '
             . 'p.supplier_id, p.cost_price, p.selling_price, p.image_path, p.active, '
             . 'i.quantity, i.reorder_level, i.location, '
             . 'c.name AS category_name, b.name AS brand_name, s.name AS supplier_name '
             . 'FROM products p '
             . 'LEFT JOIN inventory i ON i.product_id = p.product_id '
             . 'LEFT JOIN categories c ON c.category_id = p.category_id '
             . 'LEFT JOIN brands b ON b.brand_id = p.brand_id '
             . 'LEFT JOIN suppliers s ON s.supplier_id = p.supplier_id';
    }

    /**
     * @return array{0:string, 1:string, 2:array<int, mixed>}
     */
    private function buildFilter(?string $q, ?int $categoryId): array
    {
        $conditions = [];
        $types = '';
        $params = [];

        if ($q !== null && $q !== '') {
            $conditions[] = '(p.name LIKE ? OR p.sku LIKE ?)';
            $like = '%' . $q . '%';
            $types .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }
        if ($categoryId !== null) {
            $conditions[] = 'p.category_id = ?';
            $types .= 'i';
            $params[] = $categoryId;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $types, $params];
    }

    private function generateSku(string $name): string
    {
        $base = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $name));
        $base = $base !== '' ? substr($base, 0, 6) : 'PRD';

        do {
            $sku = $base . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while ($this->skuExists($sku));

        return $sku;
    }

    private function skuExists(string $sku): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM products WHERE sku = ? LIMIT 1');
        $stmt->bind_param('s', $sku);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}
