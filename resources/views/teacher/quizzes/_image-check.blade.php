<script>
    window.quizImageProblem = function (file) {
        const maxKilobytes = {{ \App\Http\Requests\StoreQuizQuestionsRequest::MAX_IMAGE_KILOBYTES }};
        const allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];

        if (!allowedTypes.includes(file.type)) {
            return 'Gambar "' + file.name + '" harus berformat PNG, JPG, atau WEBP.';
        }

        if (file.size > maxKilobytes * 1024) {
            const megabytes = (file.size / 1048576).toFixed(1).replace('.', ',');

            return 'Gambar "' + file.name + '" berukuran ' + megabytes + ' MB. Maksimal ' + (maxKilobytes / 1024) + ' MB.';
        }

        return null;
    };

    window.quizShowImagePreview = function (preview, file) {
        if (preview.src.startsWith('blob:')) {
            URL.revokeObjectURL(preview.src);
        }

        if (file) {
            preview.src = URL.createObjectURL(file);
        } else {
            preview.removeAttribute('src');
        }

        preview.classList.toggle('d-none', !file);
    };
</script>
