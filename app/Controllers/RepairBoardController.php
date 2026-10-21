<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\Customer;
use App\Models\Device;
use App\Models\RepairJob;
use App\Models\RepairNote;
use App\Models\User;
use DateTime;

class RepairBoardController extends Controller
{
    public function index(): void
    {
        $user = AuthMiddleware::requireRole('technician', 'admin');

        $technicianId = ($_GET['technician'] ?? '') !== '' ? (int) $_GET['technician'] : null;
        $isTechnician = ($user['user_type'] ?? '') === 'technician';
        if ($isTechnician) {
            $technicianId = (int) ($user['user_id'] ?? 0);
        }

        $this->renderBoard($user, $technicianId, 'repair-board', '/technician/board', $isTechnician);
    }

    public function allJobs(): void
    {
        $user = AuthMiddleware::requireRole('technician', 'admin');

        $technicianId = ($_GET['technician'] ?? '') !== '' ? (int) $_GET['technician'] : null;
        $this->renderBoard($user, $technicianId, 'all-jobs', '/technician/all-jobs', false);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function renderBoard(array $user, ?int $technicianId, string $active, string $boardUrl, bool $hideTechnicianFilter): void
    {
        $urgency = trim((string) ($_GET['urgency'] ?? ''));
        $search = trim((string) ($_GET['search'] ?? ''));

        $model = new RepairJob();
        $jobs = $model->all($technicianId, $urgency !== '' ? $urgency : null, $search !== '' ? $search : null);

        $pending = array_filter($jobs, fn(array $j): bool => in_array($j['status'], ['received', 'diagnosing'], true));
        $inProgress = array_filter($jobs, fn(array $j): bool => $j['status'] === 'in_progress');
        $completed = array_filter($jobs, fn(array $j): bool => $j['status'] === 'completed');

        $activeCount = count($pending) + count($inProgress);
        $overdue = $model->countOverdue();
        $dueToday = $model->countDueToday();
        $techs = (new User())->byRole('technician');

        $this->view('technician/board/index', [
            'user' => $user,
            'active' => $active,
            'today' => (new DateTime('now'))->format('M j, Y'),
            'pending' => array_values($pending),
            'inProgress' => array_values($inProgress),
            'completed' => array_values($completed),
            'activeCount' => $activeCount,
            'overdue' => $overdue,
            'dueToday' => $dueToday,
            'techs' => $techs,
            'technicianId' => $technicianId,
            'urgency' => $urgency,
            'search' => $search,
            'flash' => $_SESSION['flash'] ?? null,
            'hideTechnicianFilter' => $hideTechnicianFilter,
            'pageTitle' => $active === 'all-jobs' ? 'All Jobs' : 'Repair Job Board',
            'boardUrl' => $boardUrl,
        ]);
        unset($_SESSION['flash']);
    }

    public function showCreateJob(): void
    {
        $user = AuthMiddleware::requireRole('technician', 'admin');

        $techs = (new User())->byRole('technician');
        $formOld = $_SESSION['form_old'] ?? [];
        $formError = $_SESSION['form_error'] ?? null;
        unset($_SESSION['form_old'], $_SESSION['form_error']);

        $this->view('technician/board/create', [
            'user' => $user,
            'active' => 'repair-board',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'techs' => $techs,
            'formOld' => $formOld,
            'formError' => $formError,
        ]);
    }

    public function createJob(): void
    {
        AuthMiddleware::requireRole('technician', 'admin');

        $deviceBrand = trim((string) ($_POST['device_brand'] ?? ''));
        $deviceModel = trim((string) ($_POST['device_model'] ?? ''));
        $imei = trim((string) ($_POST['imei'] ?? ''));
        $reportedIssue = trim((string) ($_POST['reported_issue'] ?? ''));
        $estimatedCost = (float) ($_POST['estimated_cost'] ?? 0);
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        $urgency = trim((string) ($_POST['urgency'] ?? 'normal'));
        $technicianId = ((string) ($_POST['technician_id'] ?? '') !== '') ? (int) $_POST['technician_id'] : null;
        $customerId = ((string) ($_POST['customer_id'] ?? '') !== '') ? (int) $_POST['customer_id'] : null;
        $customerName = trim((string) ($_POST['customer_name'] ?? ''));
        $customerPhone = trim((string) ($_POST['customer_phone'] ?? ''));
        $customerEmail = trim((string) ($_POST['customer_email'] ?? ''));

        $errors = [];
        if ($deviceBrand === '') $errors[] = 'Device brand is required.';
        if ($deviceModel === '') $errors[] = 'Device model is required.';
        if ($reportedIssue === '') $errors[] = 'Issue description is required.';
        if (($customerId === null || $customerId <= 0) && $customerName === '') {
            $errors[] = 'A customer must be selected or created.';
        }
        if ($estimatedCost < 0) $errors[] = 'Cost estimate must be zero or greater.';
        if (!in_array($urgency, ['low', 'normal', 'urgent'], true)) $errors[] = 'Invalid urgency.';

        if ($errors) {
            $_SESSION['form_error'] = implode(' ', $errors);
            $_SESSION['form_old'] = $_POST;
            $this->redirect('/technician/board/create');
            return;
        }

        try {
            $repairId = (new Device())->create([
                'device_brand' => $deviceBrand,
                'device_model' => $deviceModel,
                'imei' => $imei !== '' ? $imei : null,
                'reported_issue' => $reportedIssue,
                'estimated_cost' => $estimatedCost,
                'due_date' => $dueDate !== '' ? $dueDate : null,
                'urgency' => $urgency,
                'technician_id' => $technicianId,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
            ]);

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Repair job #' . $repairId . ' created.'];
            $this->redirect('/technician/board');
        } catch (\Throwable $e) {
            $_SESSION['form_error'] = $e->getMessage();
            $_SESSION['form_old'] = $_POST;
            $this->redirect('/technician/board/create');
        }
    }

    public function searchCustomer(): void
    {
        AuthMiddleware::requireRole('technician', 'admin');

        $q = trim((string) ($_GET['q'] ?? ''));
        $results = [];
        if ($q !== '') {
            $results = (new Customer())->search($q);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['customers' => $results], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function move(): void
    {
        AuthMiddleware::requireRole('technician', 'admin');

        $input = json_decode((string) file_get_contents('php://input'), true);
        $repairId = (int) ($input['repair_id'] ?? 0);
        $column = (string) ($input['column'] ?? '');

        $allowedColumns = ['pending', 'in_progress', 'completed'];
        if ($repairId <= 0 || !in_array($column, $allowedColumns, true)) {
            $this->jsonError('Invalid request.');
            return;
        }

        $job = (new RepairJob())->find($repairId);
        if ($job === null) {
            $this->jsonError('Job not found.');
            return;
        }

        $map = [
            'pending' => 'received',
            'in_progress' => 'in_progress',
            'completed' => 'completed',
        ];

        $newStatus = $map[$column];
        $userId = (int) AuthMiddleware::id();

        try {
            (new RepairJob())->updateStatus($repairId, $newStatus, $userId);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'status' => $newStatus], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
        exit;
    }

    public function note(): void
    {
        AuthMiddleware::requireRole('technician', 'admin');

        $input = json_decode((string) file_get_contents('php://input'), true);
        $repairId = (int) ($input['repair_id'] ?? 0);
        $note = trim((string) ($input['note'] ?? ''));

        if ($repairId <= 0 || $note === '') {
            $this->jsonError('Repair ID and note are required.');
            return;
        }

        (new RepairJob())->addNote($repairId, $note, (int) AuthMiddleware::id());

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function viewJob(string $id): void
    {
        $user = AuthMiddleware::requireRole('technician', 'admin');
        $job = (new RepairJob())->find((int) $id);

        if ($job === null) {
            $this->redirect('/technician/board');
            return;
        }

        $notes = (new RepairNote())->getByJob((int) $id);
        $isAssigned = (int) ($job['technician_id'] ?? 0) === (int) ($user['user_id'] ?? 0);

        $this->view('technician/board/detail', [
            'user' => $user,
            'active' => 'repair-board',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'job' => $job,
            'notes' => $notes,
            'isAssigned' => $isAssigned,
            'flash' => $_SESSION['flash'] ?? null,
        ]);
        unset($_SESSION['flash']);
    }

    public function updateJob(): void
    {
        AuthMiddleware::requireRole('technician', 'admin');

        $input = json_decode((string) file_get_contents('php://input'), true);
        $repairId = (int) ($input['repair_id'] ?? 0);

        $job = (new RepairJob())->find($repairId);
        if ($job === null) {
            $this->jsonError('Job not found.');
            return;
        }

        $userId = (int) AuthMiddleware::id();
        if ((int) ($job['technician_id'] ?? 0) !== $userId) {
            $this->jsonError('You can only update jobs assigned to you.');
            return;
        }

        $estimatedCost = isset($input['estimated_cost']) ? (float) $input['estimated_cost'] : null;
        $finalCost = isset($input['final_cost']) ? (float) $input['final_cost'] : null;

        $db = \App\Config\Database::connection();
        $db->begin_transaction();
        try {
            $noteParts = [];
            if ($estimatedCost !== null) {
                $noteParts[] = 'Est. cost updated to $' . number_format($estimatedCost, 2);
            }
            if ($finalCost !== null) {
                $noteParts[] = 'Final cost updated to $' . number_format($finalCost, 2);
            }

            $sql = 'UPDATE repair_jobs SET ';
            $updates = [];
            $types = '';
            $params = [];

            if ($estimatedCost !== null) {
                $updates[] = 'estimated_cost = ?';
                $types .= 'd';
                $params[] = $estimatedCost;
            }
            if ($finalCost !== null) {
                $updates[] = 'final_cost = ?';
                $types .= 'd';
                $params[] = $finalCost;
            }

            if ($updates) {
                $sql .= implode(', ', $updates) . ' WHERE repair_id = ?';
                $types .= 'i';
                $params[] = $repairId;

                $stmt = $db->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();

                if ($noteParts) {
                    (new RepairNote())->create($repairId, implode('. ', $noteParts), $userId, $job['status']);
                }
            }

            $db->commit();

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $db->rollback();
            $this->jsonError($e->getMessage());
        }
        exit;
    }

    public function markComplete(): void
    {
        AuthMiddleware::requireRole('technician', 'admin');

        $input = json_decode((string) file_get_contents('php://input'), true);
        $repairId = (int) ($input['repair_id'] ?? 0);

        $job = (new RepairJob())->find($repairId);
        if ($job === null) {
            $this->jsonError('Job not found.');
            return;
        }

        $userId = (int) AuthMiddleware::id();
        if ((int) ($job['technician_id'] ?? 0) !== $userId) {
            $this->jsonError('You can only complete jobs assigned to you.');
            return;
        }

        if ($job['status'] === 'completed' || $job['status'] === 'delivered') {
            $this->jsonError('This job is already completed.');
            return;
        }

        try {
            (new RepairJob())->updateStatus($repairId, 'completed', $userId);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'status' => 'completed'], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
        exit;
    }

    public function notifyCustomer(): void
    {
        AuthMiddleware::requireRole('technician', 'admin');

        $input = json_decode((string) file_get_contents('php://input'), true);
        $repairId = (int) ($input['repair_id'] ?? 0);

        $job = (new RepairJob())->find($repairId);
        if ($job === null) {
            $this->jsonError('Job not found.');
            return;
        }

        if (empty($job['customer_phone'])) {
            $this->jsonError('Customer has no phone number on file.');
            return;
        }

        $db = \App\Config\Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO notifications (customer_id, repair_id, message, channel, status, sent_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $message = 'Your repair (' . ($job['ticket_no'] ?? '') . ') for ' . ($job['device_brand'] ?? '') . ' ' . ($job['device_model'] ?? '') . ' has been updated.';
        $channel = 'sms';
        $status = 'sent';
        $stmt->bind_param('iisss', $job['customer_id'], $repairId, $message, $channel, $status);
        $stmt->execute();
        $stmt->close();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function myTasks(): void
    {
        $user = AuthMiddleware::requireRole('technician', 'admin');
        $technicianId = (int) ($user['user_id'] ?? 0);

        $jobs = (new RepairJob())->all($technicianId, null, null);

        $this->view('technician/tasks/index', [
            'user' => $user,
            'active' => 'my-tasks',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'jobs' => $jobs,
            'flash' => $_SESSION['flash'] ?? null,
        ]);
        unset($_SESSION['flash']);
    }

    private function jsonError(string $message): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
