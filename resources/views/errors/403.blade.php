@include('errors.minimal', [
    'status' => 403,
    'title' => 'Acces refuse',
    'message' => 'Votre compte ne possede pas l autorisation necessaire pour afficher cette page ou realiser cette action.',
])
