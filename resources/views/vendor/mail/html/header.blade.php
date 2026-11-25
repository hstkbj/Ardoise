@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<table align="center" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto;">
<tr>
<td style="padding-right: 12px; vertical-align: middle;">
<img src="{{ rtrim(config('app.url'), '/') }}/icon-192.png" width="44" height="44" alt="" style="display: block; width: 44px; height: 44px; border-radius: 11px; border: 2px solid #FFFFFF;">
</td>
<td style="vertical-align: middle; color: #FFFFFF; font-size: 24px; font-weight: bold; letter-spacing: -0.3px;">{{ $slot }}</td>
</tr>
</table>
</a>
</td>
</tr>
