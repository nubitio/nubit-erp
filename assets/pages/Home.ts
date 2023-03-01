import {bus} from "../core/event/EventBus";
import LocalPicker from "../modules/LocalPicker";
import {EventType} from "../core/event/EventType";
import {StorageUtils} from "../core/utils/StorageUtils";

export default class Home {
    private layout = $('#layout-container');
    private sidebar = $('#sidebar');
    private menuToggle = $('#menu-toggle');
    private themeIcon = $('#theme-icon');
    private selectedMenu;
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

        fetch('/menu/' + window.userRoleId)
            .then(response => response.json())
            .then(data => {
                let menu: any[] = []
                data.forEach((item: any) => {
                    if (item.children) {
                        item.children.forEach((child: any) => {
                            child.parent = item.text;
                            child.iconCls = '';
                        })
                    }
                    menu.push(item);
                });

                let actualMenu = StorageUtils.getItem('menu');
                StorageUtils.setItem('menu', menu);
                if (menu.length !== actualMenu?.length) {
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
        let menu = JSON.parse(localStorage.getItem('menu') ?? '[]');

        if (!menu) {
            return;
        }

        this.sidebar.sidemenu({
            data: menu,
            fit: true,
            border: false,
            multiple: false,
            onSelect: function (item: any) {
                if (ref.selectedMenu !== null && ref.selectedMenu === item.url) {
                    return;
                }
                window.location.href = item.url;
            }
        });

        if (this.selectedMenu !== '') {
            let item = menu.flatMap((item: any) => item.children ?? []).find((item: any) => item.url === this.selectedMenu);
            this.sidebar.sidemenu('setSelected', item ?? menu[0]);
        }
    }

    toggle() {
        let opts = this.sidebar.sidemenu('options');
        this.setSidebarCollapsed(!opts.collapsed);
        let selectedMenu = location.pathname.split('/')[1];
        if (selectedMenu !== '') {
            let menu = JSON.parse(localStorage.getItem('menu') ?? '[]');
            let item = menu.flatMap((item: any) => item.children ?? []).find((item: any) => item.url === selectedMenu);
            this.sidebar.sidemenu('setSelected', item ?? menu[0]);
        }
    }

    setSidebarCollapsed(isCollapsed: boolean) {
        this.sidebar.sidemenu(isCollapsed ? 'collapse' : 'expand');
        let opts = this.sidebar.sidemenu('options');
        let west: any = this.layout.layout('panel', 'west');
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
        bus.on<string>(EventType.THEME_CHANGE, (payload: string) => {
                if (payload === 'dark') {
                    this.themeIcon.removeClass('fa-moon');
                    this.themeIcon.addClass('fa-sun');
                } else {
                    this.themeIcon.removeClass('fa-sun');
                    this.themeIcon.addClass('fa-moon');
                }
            }
        )

        bus.on(LocalPicker.EVENTS.PICKED, () => window.location.href = '/');

        window.onresize = () => {
            this.layout.layout('resize');
            this.sidebar.sidemenu('resize');
        }
    }

    changeLocal() {
        this.localPicker.show();
    }
}