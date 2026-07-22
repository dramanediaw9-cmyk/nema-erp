@include('errors.minimal', [
    'status' => 419,
    'title' => 'Session expiree',
    'message' => 'Votre session de securite a expire. Reconnectez-vous puis recommencez l action ; aucune donnee incomplete n a ete enregistree.',
])
