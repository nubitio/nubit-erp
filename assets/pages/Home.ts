import {bus} from "../EventBus";
import LocalPicker from "../modules/LocalPicker";
import {EventType} from "../EventType";
import {StorageUtils} from "../utils/StorageUtils";

export default class Home {
    private layout = $('#layout-container');
    private sidebar = $('#sidebar');
    private menuToggle = $('#menu-toggle');
    private themeIcon = $('#theme-icon');
    private selectedMenu = null;
    private localPicker: LocalPicker;

    constructor() {
        this.selectedMenu = location.pathname.split('/')[1];
        this.localPicker = new LocalPicker('main-content');
        this.init();
    }

    init() {
        this.menuToggle.linkbutton({
            plain: true,
            onClick: () => this.toggle()
        });

        this.setupMenu();

        fetch('/api/menu/' + window.userRoleId)
            .then(response => response.json())
            .then(data => {
                let menu = []
                data.forEach(item => {
                    if (item.children) {
                        item.children.forEach(child => {
                            child.parent = item.text;
                            child.iconCls = '';
                        })
                    }
                    menu.push(item);
                });

                let actualMenu = StorageUtils.getItem('menu');
                StorageUtils.setItem('menu', menu);
                if (menu.length !== actualMenu.length) {
                    this.setupMenu();
                }

                let sidebarCollapsed = localStorage.getItem('sidebarCollapsed');
                this.setSidebarCollapsed(sidebarCollapsed === 'true');
            })
            .catch(error => console.error(error));

        this.setupObservers();
    }

    setupMenu() {
        let ref = this;
        let menu = JSON.parse(localStorage.getItem('menu'));

        if (!menu) {
            return;
        }

        this.sidebar.sidemenu({
            data: menu,
            fit: true,
            border: false,
            multiple: false,
            onSelect: function (item) {
                if (ref.selectedMenu !== null && ref.selectedMenu === item.url) {
                    return;
                }
                window.location.href = item.url;
            }
        });

        if (this.selectedMenu !== '') {
            let item = menu.flatMap(item => item.children ?? []).find(item => item.url === this.selectedMenu);
            this.sidebar.sidemenu('setSelected', item ?? menu[0]);
        }
    }

    toggle() {
        let opts = this.sidebar.sidemenu('options');
        this.setSidebarCollapsed(!opts.collapsed);
        let selectedMenu = location.pathname.split('/')[1];
        if (selectedMenu !== '') {
            let menu = JSON.parse(localStorage.getItem('menu'));
            let item = menu.flatMap(item => item.children ?? []).find(item => item.url === selectedMenu);
            this.sidebar.sidemenu('setSelected', item ?? menu[0]);
        }
    }

    setSidebarCollapsed(isCollapsed: boolean) {
        this.sidebar.sidemenu(isCollapsed ? 'collapse' : 'expand');
        let opts = this.sidebar.sidemenu('options');
        let west = this.layout.layout('panel', 'west');
        west.panel('resize', {
            width: opts.collapsed ? 50 : 250
        });
        this.layout.layout('resize');
        this.sidebar.sidemenu('resize', {
            width: opts.collapsed ? 50 : 250
        });
        localStorage.setItem('sidebarCollapsed', opts.collapsed);
    }

    setupObservers() {
        bus.on(
            event => event.name === EventType.THEME_CHANGE,
            (event) => {
                if (event.data === 'dark') {
                    this.themeIcon.removeClass('fa-moon');
                    this.themeIcon.addClass('fa-sun');
                } else {
                    this.themeIcon.removeClass('fa-sun');
                    this.themeIcon.addClass('fa-moon');
                }
            }
        )

        bus.on(
            event => event.name === LocalPicker.EVENTS.PICKED,
            () => {
                window.location.href = '/';
            }
        );

        window.onresize = () => {
            this.layout.layout('resize');
            this.sidebar.sidemenu('resize');
        }
    }

    changeLocal() {
        this.localPicker.show();
    }
}