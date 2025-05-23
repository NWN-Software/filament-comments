<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>{{ $subject }}</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;">

    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 95%; margin: 0 auto;  border-radius: 6px;">
        <tr>
            <td style="padding-top: 5px; padding-bottom: 5px;">
                <p>
                    {{ Str::of($mailContent)->toHtmlString() }}
                </p>
            </td>
        </tr>
    </table>

    @if ($url)
        <br>
        <br>
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 95%; margin: 0 auto;  border-radius: 6px;">
            <tr>
                <td style="padding-top: 5px; padding-bottom: 5px;">
                    <p>
                        {!! __('filament-comments::filament-comments.mail_url_text', ['url' => $url], locale: $language) !!}
                    </p>
                </td>
            </tr>
        </table>
    @endif
</body>

</html>
