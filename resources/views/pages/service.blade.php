@if ($servicePhotos->count() > 1)
  @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  @endpush
@endif

@include('pages.standard', ['servicePhotos' => $servicePhotos])
