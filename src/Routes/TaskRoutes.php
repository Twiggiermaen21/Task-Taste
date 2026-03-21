<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

return function (\Slim\Routing\RouteCollectorProxy $group, PDO $pdo) {
    $group->get('/tasks', function (Request $request, Response $response) use ($pdo) {
        return Twig::fromRequest($request)->render($response, 'tasks.twig', ['groups' => getTaskGroups($pdo, $_SESSION['user_id']), 'active_tab' => 'tasks']);
    });

    $group->post('/tasks/group', function (Request $request, Response $response) use ($pdo) {
        $name = trim($request->getParsedBody()['name'] ?? '');
        if ($name !== '')
            $pdo->prepare("INSERT INTO task_groups (name, user_id) VALUES (?, ?)")->execute([$name, $_SESSION['user_id']]);
        return Twig::fromRequest($request)->render($response, 'partials/tasks_content.twig', ['groups' => getTaskGroups($pdo, $_SESSION['user_id'])]);
    });

    $group->delete('/tasks/group/{id}', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $pdo->prepare("DELETE FROM task_groups WHERE id = ? AND user_id = ?")->execute([$id, $_SESSION['user_id']]);
        return $response->withHeader('HX-Redirect', '/tasks')->withStatus(302);
    });

    $group->get('/tasks/{id}', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT * FROM task_groups WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $tgroup = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tgroup)
            return $response->withHeader('Location', '/tasks')->withStatus(302);

        $stmt2 = $pdo->prepare("SELECT t.*, tg.name as group_name FROM tasks t JOIN task_groups tg ON t.group_id = tg.id WHERE t.group_id = ? ORDER BY t.is_completed ASC, t.due_date ASC");
        $stmt2->execute([$id]);
        $tasks = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        if (!empty($tasks)) {
            $grouped[$tasks[0]['group_name']] = $tasks;
        } else {
            $grouped[$tgroup['name']] = [];
        }

        return Twig::fromRequest($request)->render($response, 'task_group_view.twig', [
            'group' => $tgroup,
            'grouped_tasks' => $grouped,
            'active_tab' => 'tasks',
            'is_detail_view' => true
        ]);
    });

    $group->post('/tasks/{group_id}/task', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['group_id'];
        $check = $pdo->prepare("SELECT id FROM task_groups WHERE id = ? AND user_id = ?");
        $check->execute([$id, $_SESSION['user_id']]);
        if ($check->fetch()) {
            $data = $request->getParsedBody();
            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');
            $color = trim($data['color'] ?? '');
            $due_date = trim($data['due_date'] ?? '');

            if ($title !== '') {
                $pdo->prepare("INSERT INTO tasks (group_id, title, description, color, due_date) VALUES (?, ?, ?, ?, ?)")->execute([$id, $title, $description, $color, $due_date]);
            }
        }
        $stmt2 = $pdo->prepare("SELECT t.*, tg.name as group_name FROM tasks t JOIN task_groups tg ON t.group_id = tg.id WHERE t.group_id = ? ORDER BY t.is_completed ASC, t.due_date ASC");
        $stmt2->execute([$id]);
        $tasks = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        if (!empty($tasks)) {
            $grouped[$tasks[0]['group_name']] = $tasks;
        }

        return Twig::fromRequest($request)->render($response, 'partials/tasks_items.twig', ['grouped_tasks' => $grouped]);
    });

    $group->delete('/tasks/task/{id}', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT t.image, t.group_id FROM tasks t JOIN task_groups tg ON t.group_id = tg.id WHERE t.id = ? AND tg.user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        if ($task = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$id]);
            $g_id = $task['group_id'];

            $stmt3 = $pdo->prepare("SELECT t.*, tg.name as group_name FROM tasks t JOIN task_groups tg ON t.group_id = tg.id WHERE t.group_id = ? ORDER BY t.is_completed ASC, t.due_date ASC");
            $stmt3->execute([$g_id]);
            $tasks = $stmt3->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            if (!empty($tasks)) {
                $grouped[$tasks[0]['group_name']] = $tasks;
            } else {
                // Fetch group name even if no tasks left
                $st = $pdo->prepare("SELECT name FROM task_groups WHERE id = ?");
                $st->execute([$g_id]);
                $g_name = $st->fetchColumn();
                $grouped[$g_name] = [];
            }

            return Twig::fromRequest($request)->render($response, 'partials/tasks_items.twig', ['grouped_tasks' => $grouped]);
        }
        return $response->withStatus(404);
    });

    $group->get('/tasks/task/{id}/edit', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT t.*, tg.id as group_id FROM tasks t JOIN task_groups tg ON t.group_id = tg.id WHERE t.id = ? AND tg.user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task)
            return $response->withHeader('Location', '/tasks')->withStatus(302);

        return Twig::fromRequest($request)->render($response, 'task_edit.twig', [
            'task' => $task,
            'active_tab' => 'tasks'
        ]);
    });

    $group->post('/tasks/task/{id}/edit', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT t.*, tg.id as group_id FROM tasks t JOIN task_groups tg ON t.group_id = tg.id WHERE t.id = ? AND tg.user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        if ($task = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data = $request->getParsedBody();
            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');
            $color = trim($data['color'] ?? '');
            $due_date = trim($data['due_date'] ?? '');

            $pdo->prepare("UPDATE tasks SET title = ?, description = ?, color = ?, due_date = ? WHERE id = ?")
                ->execute([$title, $description, $color, $due_date, $id]);
            return $response->withHeader('Location', '/tasks/' . $task['group_id'])->withStatus(302);
        }
        return $response->withHeader('Location', '/tasks')->withStatus(302);
    });

    $group->patch('/tasks/{id}/toggle', function (Request $request, Response $response, $args) use ($pdo) {
        $id = (int) $args['id'];
        $stmt = $pdo->prepare("SELECT t.is_completed, t.group_id FROM tasks t JOIN task_groups tg ON t.group_id = tg.id WHERE t.id = ? AND tg.user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        if ($task = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("UPDATE tasks SET is_completed = ? WHERE id = ?")->execute([$task['is_completed'] ? 0 : 1, $id]);
            $group_id = $task['group_id'];

            $stmt3 = $pdo->prepare("SELECT t.*, tg.name as group_name FROM tasks t JOIN task_groups tg ON t.group_id = tg.id WHERE t.group_id = ? ORDER BY t.is_completed ASC, t.due_date ASC");
            $stmt3->execute([$group_id]);
            $tasks = $stmt3->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            if (!empty($tasks)) {
                $grouped[$tasks[0]['group_name']] = $tasks;
            }

            return Twig::fromRequest($request)->render($response, 'partials/tasks_items.twig', ['grouped_tasks' => $grouped]);
        }
        return $response->withStatus(404);
    });
};
