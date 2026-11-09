<x-mail::message>
# Download {{ $issue->title }}

Reading {{ $issue->publication }} on Shooting Sports is free. This link downloads the PDF.

<x-mail::button :url="$downloadUrl">
Download the PDF
</x-mail::button>

The link expires in {{ $linkDays }} days. You can keep reading the issue in the browser without it.

@include('mail.partials.footer-transactional')
</x-mail::message>
