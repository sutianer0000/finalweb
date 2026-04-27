(function () {
    function formatMoney(value) {
        const digits = String(value || '').replace(/[^\d]/g, '');
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function attachMoneyFormatter(input) {
        const update = () => {
            const formatted = formatMoney(input.value);
            if (input.value !== formatted) {
                input.value = formatted;
            }
        };

        input.addEventListener('input', update);
        input.addEventListener('blur', update);
        update();
    }

    document.querySelectorAll('[data-money-input]').forEach(attachMoneyFormatter);
})();
