<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/db.php';

// Validation du token API (stocké en config ou variable env)
$apiKey = getenv('API_KEY') ?: config_value('api_key', 'default_secret_key');

// Récupère le token fourni
$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

if (!$providedKey || $providedKey !== $apiKey) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Parse JSON payload
$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Valide les paramètres requis
$matchId = $payload['match_id'] ?? null;
$score1 = $payload['score_equipe_1'] ?? null;
$score2 = $payload['score_equipe_2'] ?? null;
$minute = $payload['minute_actuelle'] ?? null;
$statut = $payload['statut'] ?? null;

if (!$matchId || $matchId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid match_id']);
    exit;
}

if (!in_array($statut, ['scheduled', 'live', 'finished'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid statut']);
    exit;
}

// Valide les scores si fournis
if ($score1 !== null && ($score1 < 0 || $score1 > 999)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid score_equipe_1']);
    exit;
}

if ($score2 !== null && ($score2 < 0 || $score2 > 999)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid score_equipe_2']);
    exit;
}

if ($minute !== null && ($minute < 0 || $minute > 120)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid minute_actuelle']);
    exit;
}

// UPDATE le match en base de données
try {
    $stmt = $pdo->prepare(
        'UPDATE matchs SET score_equipe_1 = :score1, score_equipe_2 = :score2, 
         minute_actuelle = :minute, statut = :statut, is_active = 1 WHERE id = :id'
    );
    
    $stmt->execute([
        ':score1' => $score1,
        ':score2' => $score2,
        ':minute' => $minute,
        ':statut' => $statut,
        ':id' => (int)$matchId,
    ]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Match not found']);
        exit;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Score updated successfully',
        'match_id' => (int)$matchId,
        'score' => "{$score1} - {$score2}",
        'statut' => $statut,
        'minute' => $minute
    ]);
    
} catch (Throwable $e) {
    error_log('Score update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
    exit;
}
