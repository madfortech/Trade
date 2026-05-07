window.G = {

    autoOn : true,

    refreshSeconds : 5,

    timer : null,

    countdown : null,

    expiry :

        document.getElementById(
            'expirySelect'
        )?.value || '',

    spot : 0
};

window.REFRESH_URL =
    '/angel/sensex-chain-refresh';

