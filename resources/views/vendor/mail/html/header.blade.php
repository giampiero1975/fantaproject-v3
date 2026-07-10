@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
    <table cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto;">
        <tr>
            <td style="text-align: center; padding-bottom: 6px;">
                {{-- Icona trophy SVG inline --}}
                <div style="display:inline-block; background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%); border-radius: 14px; padding: 12px 16px; margin-bottom: 10px;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L8 6H4v4c0 3.31 2.69 6 6 6h4c3.31 0 6-2.69 6-6V6h-4L12 2z" fill="#fbbf24"/>
                        <path d="M8 6v4c0 2.21 1.79 4 4 4s4-1.79 4-4V6H8z" fill="#f59e0b"/>
                        <rect x="11" y="16" width="2" height="3" fill="#fbbf24"/>
                        <rect x="8" y="19" width="8" height="2" rx="1" fill="#fbbf24"/>
                        <path d="M4 6v4c0 1.66 1.34 3 3 3V6H4z" fill="#a78bfa"/>
                        <path d="M17 6v7c1.66 0 3-1.34 3-3V6h-3z" fill="#a78bfa"/>
                    </svg>
                </div>
            </td>
        </tr>
        <tr>
            <td style="text-align: center;">
                <span style="font-size: 26px; font-weight: 800; letter-spacing: -0.5px; color: #f8fafc;">
                    Fanta<span style="color: #a78bfa;">Oracle</span>
                </span>
            </td>
        </tr>
        <tr>
            <td style="text-align: center; padding-top: 4px;">
                <span style="font-size: 11px; color: #7c3aed; letter-spacing: 2px; text-transform: uppercase; font-weight: 600;">
                    Fantasy Football Platform
                </span>
            </td>
        </tr>
    </table>
</a>
</td>
</tr>
