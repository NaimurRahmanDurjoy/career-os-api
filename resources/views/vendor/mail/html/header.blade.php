@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if (trim($slot) === 'Laravel')
<div style="display: inline-block; width: 10px; height: 10px; background-color: #10b981; border-radius: 50%; margin-right: 8px; vertical-align: middle;"></div>
<span style="font-size: 18px; font-weight: 900; color: #0f172a; text-transform: uppercase; letter-spacing: 2px; vertical-align: middle;">Career OS Pro</span>
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
