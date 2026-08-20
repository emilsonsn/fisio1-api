<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seu acesso ao FISIO1</title>
</head>
<body style="margin:0;padding:0;background:#eef4f8;font-family:Arial,sans-serif;color:#10243e">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;background:#eef4f8">
        <tr><td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;overflow:hidden;border-radius:16px;background:#ffffff;box-shadow:0 12px 35px rgba(7,70,115,.12)">
                <tr>
                    <td style="padding:26px 32px;background:#075c9f;color:#ffffff">
                        <div style="font-size:22px;font-weight:800;letter-spacing:.5px">FISIO1</div>
                        <div style="margin-top:2px;color:#a8e5fb;font-size:11px;letter-spacing:1.4px;text-transform:uppercase">Fisioterapia</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:34px 32px">
                        <div style="color:#0798d4;font-size:11px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase">Novo acesso</div>
                        <h1 style="margin:10px 0 12px;font-size:25px;line-height:1.25">Olá, {{ $user->name }}.</h1>
                        <p style="margin:0;color:#62758c;font-size:15px;line-height:1.65">Seu usuário foi criado no sistema FISIO1. Use os dados abaixo para realizar o primeiro acesso.</p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0;border:1px solid #cfe5f1;border-radius:12px;background:#eff8fc">
                            <tr><td style="padding:18px 20px 8px;color:#62758c;font-size:12px">E-mail de acesso</td></tr>
                            <tr><td style="padding:0 20px 15px;color:#075c9f;font-size:16px;font-weight:700">{{ $user->email }}</td></tr>
                            <tr><td style="padding:0 20px 8px;color:#62758c;font-size:12px">Senha temporária</td></tr>
                            <tr><td style="padding:0 20px 18px;color:#075c9f;font-family:Consolas,Monaco,monospace;font-size:19px;font-weight:800;letter-spacing:1px">{{ $temporaryPassword }}</td></tr>
                        </table>

                        <div style="text-align:center">
                            <a href="{{ $applicationUrl }}" style="display:inline-block;padding:13px 24px;border-radius:9px;background:#0798d4;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none">Acessar o FISIO1</a>
                        </div>
                        <p style="margin:22px 0 0;color:#62758c;font-size:13px;line-height:1.6">Por segurança, altere a senha temporária depois do primeiro acesso e não compartilhe estes dados.</p>
                        <p style="margin:12px 0 0;color:#8493a5;font-size:12px;line-height:1.5">Se o botão não funcionar, acesse: <a href="{{ $applicationUrl }}" style="color:#075c9f">{{ $applicationUrl }}</a></p>
                    </td>
                </tr>
                <tr><td style="padding:18px 32px;border-top:1px solid #e3ebf0;color:#8493a5;font-size:11px">Mensagem automática e confidencial do sistema FISIO1.</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
