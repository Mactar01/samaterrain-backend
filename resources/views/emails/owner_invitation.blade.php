<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invitation myTerrain</title>
</head>
<body>
    <h2>Bonjour, {{ $invitation->business_name }} !</h2>
    <p>Vous avez été invité(e) par {{ $invitation->invited_by_name }} à rejoindre la plateforme myTerrain en tant que Gérant de terrain (Loueur).</p>
    <p>Pour créer votre compte et commencer à gérer vos terrains, veuillez cliquer sur le lien ci-dessous :</p>
    <p>
        <a href="{{ $inviteLink }}" style="display:inline-block; padding:10px 20px; background-color:#28a745; color:#fff; text-decoration:none; border-radius:5px;">Créer mon compte Loueur</a>
    </p>
    <p><em>Ce lien expirera le {{ $invitation->expires_at->format('d/m/Y à H:i') }}.</em></p>
    <p>À très bientôt sur myTerrain !</p>
</body>
</html>
