$(window).on('load', function () {
    let container = $('#layout-container');
    if (container) {
        container.animate({"opacity": 1});
    }
});

$.fn.extend({
    animateCss: function (animationName) {
        const animationEnd = 'webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend';
        this.addClass('animate__animated ' + animationName).one(animationEnd, function () {
            $(this).removeClass('animate__animated ' + animationName);
        });
    }
});

$.extend($.fn.window.defaults, {
    zIndex: 1000,
    onMove: function (left, top) {
        if (left < 0) {
            $(this).window('move', {
                left: 0
            });
        }
        if (top < 0) {
            $(this).window('move', {
                top: 0
            });
        }
    }
});

$.extend($.fn.datagrid.defaults, {
    pagination: PAGINATION,
    pageSize: PAGE_SIZE,
    rownumbers: ROW_NUMBERS,
    fitColumns: true,
    fit: true,
    border: BORDER,
    singleSelect: SINGLE_SELECT,
    multiSort: true,
    remoteFilter: true
});

$.extend($.fn.dialog.defaults, {
    width: DIALOG_WIDTH,
    height: DIALOG_HEIGHT,
    maxHeight: DIALOG_MAX_HEIGHT,
    modal: MODAL,
    closed: CLOSED,
    inline: false,
    shadow: SHADOW,
    closable: false,
    border: false,
    cls: 'card-dialog',
    onOpen: function () {
        $(this).dialog('center');
        $(this).parent().animateCss(DIALOG_OPEN_ANIMATION);
    },
    onMove: function (left, top) {
        if (left < 0) {
            $(this).dialog('move', {
                left: 0
            });
        }
        if (top < 0) {
            $(this).dialog('move', {
                top: 0
            });
        }
    }
});

$.extend($.messager.defaults, {
    border: false,
    closable: false,
    cls: 'card-dialog'
});

$.extend($.fn.panel.methods, {
    showMask: function (jq, msg) {
        return jq.each(function () {
            var pal = $(this).panel('panel');
            if (pal.css('position').toLowerCase() != 'absolute') {
                pal.css('position', 'relative');
            }
            var m = pal.children('div.panel-mask');
            if (!m.length) {
                m = $('<div class="panel-mask"></div>').appendTo(pal);
            }
            m.css({
                position: 'absolute',
                width: '100%',
                height: '100%',
                top: 0
            });
            m.children('div.panel-mask-msg').remove();
            var mm = $('<div class="panel-mask-msg text"></div>').appendTo(m);
            msg = msg + ' <i class="fas fa-circle-notch fa-spin fa-lg fa-fw"></i>';
            mm.html(msg).css({position: 'absolute'}).css({
                position: 'absolute',
                top: '50%',
                left: '50%',
                marginTop: -mm._outerHeight() / 2,
                marginLeft: -mm._outerWidth() / 2,
                padding: '10px',
            });
        });
    },
    hideMask: function (jq) {
        return jq.each(function () {
            $(this).panel('panel').children('div.panel-mask').remove();
        });
    }
});

$.extend($.fn.validatebox.defaults.rules, {
    validCustomerId: {
        validator: function (value, param) {
            return value.length === 8 || rucValidator(value);
        },
        message: 'Ingrese un RUC/DNI valido.'
    }
});

$.extend($.fn.sidemenu.methods, {
    setSelected: function (jq, item) {
        if (item) {
            let accordion = $('.sidemenu .accordion');
            accordion.accordion('select', item.parent);
            let selected = accordion.accordion('getPanel', item.parent);
            $('.sidemenu .accordion .accordion-header').each((index, element) => {
                $(element).removeClass('accordion-header-selected')
            });
            selected?.prev().addClass('accordion-header-selected');
            let tree = selected?.find('.tree');
            let node = tree?.tree('find', {text: item.text});
            tree?.tree('select', node?.target);
        }
    }
});

$.extend($.fn.textbox.methods, {
    show: function (jq) {
        return jq.each(function () {
            $(this).next().show();
            $($(this).textbox('label')).show();
        })
    },
    hide: function (jq) {
        return jq.each(function () {
            $(this).next().hide();
            $($(this).textbox('label')).hide();
        })
    }
})

function rucValidator(ruc) {
    let rucCopy = ruc;
    let validStartWith = [10, 15, 16, 17, 20];
    let rucPart = ruc.toString().substr(0, 2);
    if (ruc.length !== 11 || (!validStartWith.includes(parseInt(rucPart))))
        return false;

    let sum = 0;
    for (let i = 0; i < 11; i++) {
        if (i !== 0) {
            let factor = (i % 7 + (i / 7 | 0) + 1);
            sum += (ruc % 10) * factor;
        }
        ruc = ruc / 10 | 0
    }
    let mod = sum % 11;
    let res = 11 - mod;
    return (rucCopy % 10 === res % 10);
}
