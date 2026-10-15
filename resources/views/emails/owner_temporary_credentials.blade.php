<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Vos accès Loueur myTerrain</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
        .card { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .header { text-align: center; margin-bottom: 24px; }
        .logo { font-size: 24px; font-weight: 800; color: #059669; }
        .badge { display: inline-block; background: #ecfdf5; color: #065f46; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 9999px; margin-top: 8px; }
        .credentials-box { background: #f1f5f9; border-radius: 12px; padding: 18px 20px; margin: 20px 0; border: 1px solid #cbd5e1; }
        .credential-row { margin: 8px 0; font-size: 14px; }
        .credential-label { font-weight: 600; color: #64748b; }
        .credential-value { font-weight: 700; color: #0f172a; font-family: monospace; font-size: 15px; }
        .warning-box { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 8px; margin: 18px 0; font-size: 13px; color: #92400e; line-height: 1.5; }
        .btn-container { text-align: center; margin-top: 28px; }
        .btn { display: inline-block; padding: 12px 28px; background: linear-gradient(135deg, #059669, #10b981); color: #ffffff !important; text-decoration: none; font-weight: 700; border-radius: 12px; font-size: 14px; box-shadow: 0 2px 4px rgba(5,150,105,0.3); }
        .footer { text-align: center; font-size: 11px; color: #94a3b8; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="logo">⚽ myTerrain</div>
            <div class="badge">Espace Partenaire Loueur</div>
        </div>

        <h3 style="margin-top: 0; color: #0f172a;">Bonjour {{ $user->name }},</h3>
        <p style="font-size: 14px; line-height: 1.6; color: #475569;">
            Votre compte Loueur Partenaire a été créé avec succès pour votre complexe sportif <strong>{{ $businessName }}</strong>.
        </p>

        <p style="font-size: 14px; color: #475569;">Voici vos identifiants pour accéder à votre espace de gestion :</p>

        <div class="credentials-box">
            <div class="credential-row">
                <span class="credential-label">📧 Identifiant (Email) :</span><br>
                <span class="credential-value">{{ $user->email }}</span>
            </div>
            <div class="credential-row" style="margin-top: 12px;">
                <span class="credential-label">🔑 Mot de passe temporaire :</span><br>
                <span class="credential-value">{{ $temporaryPassword }}</span>
            </div>
        </div>

        <div class="warning-box">
            <strong>⚠️ Sécurité — Première connexion obligatoire :</strong><br>
            Ce mot de passe est <strong>strictement temporaire</strong>. Dès votre première connexion, la plateforme vous demandera de définir un nouveau mot de passe personnel et sécurisé pour débloquer l'accès à votre espace.
        </div>

        <div class="btn-container">
            <a href="{{ $loginLink }}" class="btn">Se connecter à mon espace Loueur</a>
        </div>

        <div class="footer">
            Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
            <a href="{{ $loginLink }}" style="color: #059669;">{{ $loginLink }}</a>
            <br><br>
            © {{ date('Y') }} myTerrain. Tous droits réservés.
        </div>
    </div>
</body>
</html>
