window.toggleAutoRefresh = function(force = null){

    if(force !== null){

        G.autoOn = force;

    }else{

        G.autoOn = !G.autoOn;
    }

    const btn =
        document.getElementById(
            'autoRefreshBtn'
        );

    const cd =
        document.getElementById(
            'refreshCountdown'
        );

    if(G.autoOn){

        btn.textContent = 'ON';

        btn.classList.remove(
            'bg-gray-200',
            'text-gray-600'
        );

        btn.classList.add(
            'bg-green-500',
            'text-white'
        );

        if(cd){

            cd.classList.remove(
                'hidden'
            );
        }

        _startAutoRefreshCycle();

    }else{

        btn.textContent = 'OFF';

        btn.classList.remove(
            'bg-green-500',
            'text-white'
        );

        btn.classList.add(
            'bg-gray-200',
            'text-gray-600'
        );

        clearTimeout(G.timer);

        clearInterval(G.countdown);

        if(cd){

            cd.classList.add(
                'hidden'
            );
        }
    }
};

window._startAutoRefreshCycle = function(){

    clearTimeout(G.timer);

    clearInterval(G.countdown);

    let left =
        G.refreshSeconds;

    const cd =
        document.getElementById(
            'refreshCountdown'
        );

    if(cd){

        cd.textContent =
            left + 's';
    }

    G.countdown = setInterval(()=>{

        left--;

        if(cd){

            cd.textContent =
                left + 's';
        }

    },1000);

    G.timer = setTimeout(

        _doChainRefresh,

        G.refreshSeconds * 1000
    );
};

