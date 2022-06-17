const date = new Date(), y = date.getFullYear(), m = date.getMonth();
const firstDay = new Date(y, m, 1);
const lastDay = new Date(y, m + 1, 0);

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
    striped: STRIPED,
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
    inline: INLINE,
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
    validDate: {
        validator: function (value) {
            var date = dateparser(value);
            var s = dateformatter(date);
            return s == value;
        },
        message: 'Por favor ingrese una fecha valida'
    }
});

$.extend($.fn.validatebox.defaults.rules, {
    confirmPass: {
        validator: function (value, param) {
            var pass = $(param[0]).passwordbox('getValue');
            return value == pass;
        },
        message: 'La confrmación no coincide con la contraseña.'
    }
});

$.extend($.fn.validatebox.defaults.rules, {
    checkProtected: {
        validator: function (value, param) {
            return !param[0].includes(value.toUpperCase());
        },
        message: 'El valor no esta permitido.'
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
    select: function (jq, item) {
        let accordion = $('.sidemenu .accordion');
        accordion.accordion('select', item.parent);
        let selected = accordion.accordion('getPanel', item.parent);
        $('.sidemenu .accordion .accordion-header').each((index, element) => {
            $(element).removeClass('accordion-header-selected')
        });
        selected.prev().addClass('accordion-header-selected');
        let tree = selected.find('.tree');
        let node = tree.tree('find', {text: item.text});
        tree.tree('select', node.target);
    }
});

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

function dateformatter(date) {
    var y = date.getFullYear();
    var m = date.getMonth() + 1;
    var d = date.getDate();
    return y + '-' + (m < 10 ? ('0' + m) : m) + '-' + (d < 10 ? ('0' + d) : d);
}

function dateparser(s) {
    if (!s) return new Date();
    var ss = (s.split('-'));
    var y = parseInt(ss[0], 10);
    var m = parseInt(ss[1], 10);
    var d = parseInt(ss[2], 10);
    if (!isNaN(y) && !isNaN(m) && !isNaN(d)) {
        return new Date(y, m - 1, d);
    } else {
        return new Date();
    }
}

function activeFormatter(val) {
    if (Number(val) === 1) {
        return '<span class="badge rounded-pill bg-success">SI</span>';
    } else {
        return '<span class="badge rounded-pill bg-danger">NO</span>';
    }
}

function typeFormatter(tipo_id) {
    if (Number(tipo_id) === 1) {
        return '<span class="badge bg-primary">INGRESO</span>';
    } else {
        return '<span class="badge bg-secondary">SALIDA</span>';
    }
}

function typeWarehouseFormatter(tipo_id) {
    if (Number(tipo_id) === 2) {
        return '<span class="badge bg-bg-secondary">INSUMOS</span>';
    } else {
        return '<span class="badge bg-primary">PRODUCTOS</span>';
    }
}

function imageFormatter(val, row) {
    if (val) {
        return `<a href="${val}" data-lightbox="${row.descripcion}">
                    <img src="${val}" style="width: 30px; height: 30px;"  alt=""/>
                </a>`;
    } else {
        return `<a href="https://ui-avatars.com/api/?name=${row.descripcion}" data-lightbox="${row.descripcion}">
                    <img src="https://ui-avatars.com/api/?name=${row.descripcion}" style="width: 30px; height: 30px;"  alt=""/>
                </a>`;
    }
}

function show_info(message) {
    $.messager.show({
        title: 'Info',
        msg: message
    });
}

function show_error(message) {
    $.messager.show({
        title: 'Error',
        msg: message
    });
}

function disableButtons(btnOk, btnCancel, dialog) {
    btnCancel.linkbutton('disable');
    btnOk.linkbutton('disable');
    btnOk.linkbutton({text: 'Guardando...'});
    dialog.dialog('showMask', 'Procesando...');
}

function enableButtons(btnOk, btnCancel, dialog) {
    btnCancel.linkbutton('enable');
    btnOk.linkbutton('enable');
    btnOk.linkbutton({text: 'Guardar'});
    dialog.dialog('hideMask');
}

const loadData = async (url, key, value) => {
    try {
        let result = [];
        const response = await fetch(url);
        const data = await response.json();
        data.forEach(element => {
            result.push({
                value: element[key],
                text: element[value]
            });
        });
        return result;
    } catch (error) {
        console.log(error);
    }
};
