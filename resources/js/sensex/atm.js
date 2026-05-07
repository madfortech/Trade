window.updateAtmHighlight = function(

    row,
    strike,
    atm

){

    row.classList.remove(

        'atm-row',

        'ring-2',

        'ring-orange-400',

        'ring-inset',

        'bg-orange-100/70'
    );

    if(strike === atm){

        row.classList.add(

            'atm-row',

            'ring-2',

            'ring-orange-400',

            'ring-inset',

            'bg-orange-100/70'
        );
    }
};

