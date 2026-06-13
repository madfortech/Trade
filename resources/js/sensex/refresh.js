window._doChainRefresh = async function(){

    if(!G.autoOn)return;

    try{

        const r = await fetch(

            REFRESH_URL +
            '?expiry=' +
            encodeURIComponent(G.expiry),

            {
                headers:{
                    'X-Requested-With':'XMLHttpRequest'
                }
            }
        );

        const j = await r.json();

        if(!j.success)
            throw new Error(j.message);

        /*
        |--------------------------------------------------------------------------
        | UPDATE SPOT
        |--------------------------------------------------------------------------
        */

        G.spot = j.sensexSpot;

        const sv =
            document.getElementById(
                'sensexSpotValue'
            );

        if(sv){

            sv.textContent =
                parseFloat(
                    G.spot
                ).toLocaleString(
                    'en-IN',
                    {
                        minimumFractionDigits:2
                    }
                );
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE TABLE
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '#chainBody tr[data-strike]'
            )

            .forEach(row=>{

                const strike =
                    parseInt(
                        row.dataset.strike
                    );

                const d =
                    j.data[strike];

                if(!d)return;

                /*
                |--------------------------------------------------------------------------
                | ATM
                |--------------------------------------------------------------------------
                */

                updateAtmHighlight(

                    row,

                    strike,

                    parseInt(j.atm)
                );

                /*
                |--------------------------------------------------------------------------
                | CE / PE
                |--------------------------------------------------------------------------
                */

                ['ce','pe'].forEach(t=>{

                    if(!d[t])return;

                    const lEl =
                        row.querySelector(
                            `[data-ltp="${t}"]`
                        );

                    const cEl =
                        row.querySelector(
                            `[data-chg="${t}"]`
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | LTP
                    |--------------------------------------------------------------------------
                    */

                    if(lEl){

                        const v =
                            parseFloat(
                                d[t].ltp
                            );

                        lEl.textContent =
                            v > 0
                            ? v.toFixed(2)
                            : '—';

                        lEl.classList.add(
                            'ltp-flash'
                        );

                        setTimeout(()=>{

                            lEl.classList.remove(
                                'ltp-flash'
                            );

                        },700);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CHANGE %
                    |--------------------------------------------------------------------------
                    */

                    if(
                        cEl &&
                        d[t].ltp > 0
                    ){

                        const c =
                            d[t].percentChange;

                        cEl.textContent =

                            (
                                c >= 0
                                ? '▲'
                                : '▼'
                            )

                            +

                            Math.abs(c)
                                .toFixed(2)

                            +

                            '%';

                        cEl.style.color =

                            c >= 0
                            ? '#22c55e'
                            : '#ef4444';
                    }
                });

                /*
                |--------------------------------------------------------------------------
                | STRIKE CELL
                |--------------------------------------------------------------------------
                */

                const strikeCell =
                    row.querySelector(
                        'td:nth-child(2) div'
                    );

                // if(strikeCell){

                //     strikeCell.innerHTML = `
                    

                //         ${
                //             strike === parseInt(j.atm)

                //             ? '<span class="text-orange-600">🔵</span>'

                //             : ''
                //         }

                //         ${strike.toLocaleString('en-IN')}
                //     `;
                // }
                if(strikeCell){

                    strikeCell.innerHTML = `

                        ${
                            strike === parseInt(j.atm)

                            ? '<span class="text-orange-600">🔵</span>'

                            : ''
                        }

                        ${strike.toLocaleString('en-IN')}
                    `;

                    strikeCell.style.cursor = 'pointer';

                    strikeCell.onclick = () => {

                        if(typeof window.openSensexChart === 'function'){

                            window.openSensexChart({

                                strike,

                                expiry: G.expiry
                            });
                        }
                    };
                }
            });

        /*
        |--------------------------------------------------------------------------
        | LAST UPDATED
        |--------------------------------------------------------------------------
        */

        const lu =
            document.getElementById(
                'lastUpdated'
            );

        if(lu){

            lu.textContent =
                'Updated: ' + j.time;
        }

    }catch(e){

        console.warn(
            'Refresh:',
            e.message
        );
    }

    /*
    |--------------------------------------------------------------------------
    | RESTART TIMER
    |--------------------------------------------------------------------------
    */

    if(G.autoOn){

        _startAutoRefreshCycle();
    }
};

