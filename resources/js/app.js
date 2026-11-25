const preparedItemRequestPdfs = new Map();

window.prepareItemRequestPdf = async (pdfUrl, code) => {
    if (preparedItemRequestPdfs.has(pdfUrl)) {
        return;
    }

    const response = await fetch(pdfUrl);

    if (!response.ok) {
        throw new Error('PDF permintaan tidak dapat diunduh.');
    }

    const blob = await response.blob();
    const safeCode = code.replace(/[^a-zA-Z0-9_-]/g, '-');
    const fileName = `Permintaan-${safeCode}.pdf`;
    const file = typeof File === 'function'
        ? new File([blob], fileName, { type: 'application/pdf' })
        : null;

    preparedItemRequestPdfs.set(pdfUrl, { blob, file, fileName });
};

window.shareItemRequestPdf = async (pdfUrl, code, message) => {
    const preparedPdf = preparedItemRequestPdfs.get(pdfUrl);

    if (!preparedPdf) {
        throw new Error('PDF belum siap. Muat ulang halaman, lalu coba lagi.');
    }

    const { blob, file, fileName } = preparedPdf;
    const canShareFiles = file
        && typeof navigator !== 'undefined'
        && typeof navigator.canShare === 'function'
        && navigator.canShare({ files: [file] });

    if (canShareFiles && typeof navigator.share === 'function') {
        try {
            await navigator.share({
                files: [file],
                title: `Permintaan ${code}`,
                text: `${message}\nPDF terlampir.`,
            });
            return 'shared';
        } catch (error) {
            const errorName = error && typeof error === 'object' && 'name' in error
                ? error.name
                : '';

            if (errorName === 'AbortError') {
                throw error;
            }
        }
    }

    const objectUrl = URL.createObjectURL(blob);
    const downloadLink = document.createElement('a');
    downloadLink.href = objectUrl;
    downloadLink.download = fileName;
    document.body.append(downloadLink);
    downloadLink.click();
    downloadLink.remove();
    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 5_000);

    const fallbackMessage = `${message}\n\nLampirkan PDF: ${fileName}`;

    window.open(`https://wa.me/?text=${encodeURIComponent(fallbackMessage)}`, '_blank', 'noopener');
    return 'downloaded';
};
