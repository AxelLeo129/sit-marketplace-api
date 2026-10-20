{{-- Encabezado de todos los correos (recetas, recuperar contraseña) con el logo de SIT. --}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/sit-logo.png') }}" class="logo" alt="SIT"><br>
{!! $slot !!}
</a>
</td>
</tr>
