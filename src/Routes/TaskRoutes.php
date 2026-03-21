<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

return function (\Slim\Routing\RouteCollectorProxy $group, \MongoDB\Database $db) {
    function getTasksForGroup(\MongoDB\Database $db, string $groupId, string $groupName) {
        $tasks = [];
        foreach ($db->tasks->find(['group_id' => $groupId]) as $t) {
            $t['id'] = (string)$t['_id'];
            $t['group_name'] = $groupName;
            $tasks[] = $t;
        }
        usort($tasks, function($a, $b) {
            if (($a['is_completed'] ?? 0) !== ($b['is_completed'] ?? 0)) {
                return ($a['is_completed'] ?? 0) <=> ($b['is_completed'] ?? 0);
            }
            $da = empty($a['due_date']) ? PHP_INT_MAX : strtotime($a['due_date']);
            $dbate = empty($b['due_date']) ? PHP_INT_MAX : strtotime($b['due_date']);
            return $da <=> $dbate;
        });
        return $tasks;
    }

    $group->get('/tasks', function (Request $request, Response $response) use ($db) {
        return Twig::fromRequest($request)->render($response, 'tasks.twig', ['groups' => getTaskGroups($db, $_SESSION['user_id']), 'active_tab' => 'tasks']);
    });

    $group->post('/tasks/group', function (Request $request, Response $response) use ($db) {
        $name = trim($request->getParsedBody()['name'] ?? '');
        if ($name !== '') {
            $db->task_groups->insertOne(['name' => $name, 'user_id' => $_SESSION['user_id']]);
        }
        return Twig::fromRequest($request)->render($response, 'partials/tasks_content.twig', ['groups' => getTaskGroups($db, $_SESSION['user_id'])]);
    });

    $group->delete('/tasks/group/{id}', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $db->task_groups->deleteOne(['_id' => new \MongoDB\BSON\ObjectId($id), 'user_id' => $_SESSION['user_id']]);
        $db->tasks->deleteMany(['group_id' => $id]);
        return $response->withHeader('HX-Redirect', '/tasks')->withStatus(302);
    });

    $group->get('/tasks/{id}', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $tgroup = $db->task_groups->findOne(['_id' => new \MongoDB\BSON\ObjectId($id), 'user_id' => $_SESSION['user_id']]);
        if (!$tgroup)
            return $response->withHeader('Location', '/tasks')->withStatus(302);

        $tasks = getTasksForGroup($db, (string)$tgroup['_id'], $tgroup['name']);
        
        $grouped = [];
        $grouped[$tgroup['name']] = $tasks;

        $tgroup_arr = (array)$tgroup;
        $tgroup_arr['id'] = (string)$tgroup['_id'];

        return Twig::fromRequest($request)->render($response, 'task_group_view.twig', [
            'group' => $tgroup_arr,
            'grouped_tasks' => $grouped,
            'active_tab' => 'tasks',
            'is_detail_view' => true
        ]);
    });

    $group->post('/tasks/{group_id}/task', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['group_id'];
        $tgroup = $db->task_groups->findOne(['_id' => new \MongoDB\BSON\ObjectId($id), 'user_id' => $_SESSION['user_id']]);
        if ($tgroup) {
            $data = $request->getParsedBody();
            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');
            $color = trim($data['color'] ?? '');
            $due_date = trim($data['due_date'] ?? '');

            if ($title !== '') {
                $db->tasks->insertOne([
                    'group_id' => $id,
                    'title' => $title,
                    'description' => $description,
                    'color' => $color,
                    'due_date' => $due_date,
                    'is_completed' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            $tasks = getTasksForGroup($db, $id, $tgroup['name']);
            $grouped = [$tgroup['name'] => $tasks];
            return Twig::fromRequest($request)->render($response, 'partials/tasks_items.twig', ['grouped_tasks' => $grouped]);
        }
        return $response->withStatus(404);
    });

    $group->delete('/tasks/task/{id}', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $task = $db->tasks->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
        if ($task) {
            $g_id = $task['group_id'];
            $tgroup = $db->task_groups->findOne(['_id' => new \MongoDB\BSON\ObjectId($g_id), 'user_id' => $_SESSION['user_id']]);
            if ($tgroup) {
                $db->tasks->deleteOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
                
                $tasks = getTasksForGroup($db, $g_id, $tgroup['name']);
                $grouped = [$tgroup['name'] => $tasks];
                return Twig::fromRequest($request)->render($response, 'partials/tasks_items.twig', ['grouped_tasks' => $grouped]);
            }
        }
        return $response->withStatus(404);
    });

    $group->get('/tasks/task/{id}/edit', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $task = $db->tasks->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
        if ($task) {
            $tgroup = $db->task_groups->findOne(['_id' => new \MongoDB\BSON\ObjectId($task['group_id']), 'user_id' => $_SESSION['user_id']]);
            if ($tgroup) {
                $task_arr = (array)$task;
                $task_arr['id'] = (string)$task['_id'];
                
                return Twig::fromRequest($request)->render($response, 'task_edit.twig', [
                    'task' => $task_arr,
                    'active_tab' => 'tasks'
                ]);
            }
        }
        return $response->withHeader('Location', '/tasks')->withStatus(302);
    });

    $group->post('/tasks/task/{id}/edit', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $task = $db->tasks->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
        if ($task) {
            $tgroup = $db->task_groups->findOne(['_id' => new \MongoDB\BSON\ObjectId($task['group_id']), 'user_id' => $_SESSION['user_id']]);
            if ($tgroup) {
                $data = $request->getParsedBody();
                $title = trim($data['title'] ?? '');
                $description = trim($data['description'] ?? '');
                $color = trim($data['color'] ?? '');
                $due_date = trim($data['due_date'] ?? '');

                $db->tasks->updateOne(
                    ['_id' => new \MongoDB\BSON\ObjectId($id)],
                    ['$set' => [
                        'title' => $title,
                        'description' => $description,
                        'color' => $color,
                        'due_date' => $due_date
                    ]]
                );
                return $response->withHeader('Location', '/tasks/' . (string)$task['group_id'])->withStatus(302);
            }
        }
        return $response->withHeader('Location', '/tasks')->withStatus(302);
    });

    $group->patch('/tasks/{id}/toggle', function (Request $request, Response $response, $args) use ($db) {
        $id = $args['id'];
        $task = $db->tasks->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
        if ($task) {
            $g_id = $task['group_id'];
            $tgroup = $db->task_groups->findOne(['_id' => new \MongoDB\BSON\ObjectId($g_id), 'user_id' => $_SESSION['user_id']]);
            if ($tgroup) {
                $db->tasks->updateOne(
                    ['_id' => new \MongoDB\BSON\ObjectId($id)],
                    ['$set' => ['is_completed' => ($task['is_completed'] ?? 0) ? 0 : 1]]
                );

                $tasks = getTasksForGroup($db, $g_id, $tgroup['name']);
                $grouped = [$tgroup['name'] => $tasks];
                return Twig::fromRequest($request)->render($response, 'partials/tasks_items.twig', ['grouped_tasks' => $grouped]);
            }
        }
        return $response->withStatus(404);
    });
};
