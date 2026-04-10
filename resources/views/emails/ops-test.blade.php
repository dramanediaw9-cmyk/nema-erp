<!DOCTYPE html>
<html lang="fr">
    <body style="font-family:Arial, sans-serif; color:#172033; line-height:1.6;">
        <h2 style="margin:0 0 12px;">Test email Nema ERP</h2>
        <p style="margin:0 0 12px;">
            Cet email confirme que le canal sortant de <strong>{{ $companyName }}</strong> fonctionne.
        </p>
        <p style="margin:0 0 12px;">
            Destinataire teste : <strong>{{ $recipient }}</strong><br>
            @if ($sentBy)
                Demande lancee par : <strong>{{ $sentBy }}</strong><br>
            @endif
            Date : <strong>{{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</strong>
        </p>
        <p style="margin:0;">
            Si tu reçois ce message, la configuration mail de production est operationnelle.
        </p>
    </body>
</html>
