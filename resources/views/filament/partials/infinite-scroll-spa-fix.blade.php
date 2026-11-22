<script>
    document.addEventListener('livewire:navigating', () => {
        document.getElementById('dynamic-table-height')?.remove();
    });

    document.addEventListener('livewire:navigated', () => {
        const style = document.getElementById('dynamic-table-height');
        const container = document.querySelector('.fi-ta-content-ctn');

        if (!style || !container) {
            return;
        }

        const top = container.getBoundingClientRect().top;
        const maxHeight = Math.max(window.innerHeight - top - 8, 0);

        style.innerHTML = `.fi-ta-content-ctn { max-height: ${maxHeight}px !important; }`;
    });
</script>
