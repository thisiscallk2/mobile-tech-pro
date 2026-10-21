<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Inventory model -- stock aggregates.
 * A product is "low stock" when quantity <= reorder_level (the low-stock
 * threshold documented in db.sql).
 */
class Inventory extends Model
{
    /**
     * Number of products at or below their reorder level.
     */
    public function getLowStockCount(): int
    {
        $sql = "SELECT COUNT(*) AS c FROM inventory WHERE quantity <= reorder_level";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Products at or below their reorder level, with product name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLowStockItems(): array
    {
        $sql = 'SELECT i.product_id, p.name, i.quantity, i.reorder_level '
             . 'FROM inventory i JOIN products p ON p.product_id = i.product_id '
             . 'WHERE i.quantity <= i.reorder_level ORDER BY i.quantity ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }
}
