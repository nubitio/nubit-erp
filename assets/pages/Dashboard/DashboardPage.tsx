import "./DashboardPage.scss";
import ScrollView from "devextreme-react/scroll-view";
import {
    ConversionCard,
    ConversionTicker,
    LeadsTicker,
    OpportunitiesTicker, RevenueAnalysisCard, RevenueCard, RevenueSnapshotCard,
    RevenueTotalTicker,
    ToolbarAnalytics
} from "../../components";
import {Item} from "devextreme-react/toolbar";
import {Tabs} from "devextreme-react";
import LoadPanel from "devextreme-react/load-panel";
import {useCallback, useEffect, useState} from "react";
import {ANALYTICS_PERIODS, DEFAULT_ANALYTICS_PERIOD_KEY} from "../../shared/constants";
import {useScreenSize} from "../../utils/media-query";
import {Sale, SaleByState, SaleOrOpportunityByCategory} from "../../types/analytics";

const calculateTotal = (data: (SaleOrOpportunityByCategory & Sale)[]) => {
    return data.reduce((acc, item) => acc + (item.value || item.total), 0);
};

const items = Object.keys(ANALYTICS_PERIODS);

export const DashboardPage = () => {
    const container = "wrapper"
    const [tabIndex, setTabIndex] = useState(ANALYTICS_PERIODS[DEFAULT_ANALYTICS_PERIOD_KEY].index);
    const [dateRange, setDateRange] = useState(ANALYTICS_PERIODS[DEFAULT_ANALYTICS_PERIOD_KEY].period.split('/'));
    const [opportunities, setOpportunities] = useState<SaleOrOpportunityByCategory[]>([]);
    const [salesByCategory, setSalesByCategory] = useState<SaleOrOpportunityByCategory[]>([]);
    const [sales, setSales] = useState<Sale[]>([]);
    const [salesByState, setSalesByState] = useState<SaleByState[]>([]);
    const [salesTotal, setSalesTotal] = useState(0);
    const [opportunitiesTotal, setOpportunitiesTotal] = useState(0);
    const [isLoading, setIsLoading] = useState(true);
    const [tabsWidth, setTabsWidth] = useState<number | string>('auto');

    const { isXSmall } = useScreenSize();

    useEffect(() => {
        Promise.all([

        ])
            .then(() => setIsLoading(false))
            .catch((error) => console.log(error));
    }, [dateRange]);

    const onTabClick = useCallback((e) => {
        const { index, period } = ANALYTICS_PERIODS[e.itemData];
        setTabIndex(index);
        setDateRange(period.split('/'));
        setIsLoading(true);
    }, []);

    useEffect(() => {
        setTabsWidth(isXSmall ? 150 : 'auto');
    }, []);

    return (
        <div id={container}>
            <ScrollView className='view-wrapper-scroll'>
                <ToolbarAnalytics
                    title='Dashboard'
                    additionalToolbarContent={
                        <Item
                            location='before'
                        >
                            <Tabs
                                width={tabsWidth}
                                scrollByContent
                                showNavButtons={false}
                                dataSource={items}
                                selectedIndex={tabIndex}
                                onItemClick={onTabClick}
                            />
                        </Item>
                    }
                >
                    <div className='cards compact'>
                        <OpportunitiesTicker value={opportunitiesTotal} />
                        <RevenueTotalTicker value={salesTotal} />
                        <ConversionTicker value={16} />
                        <LeadsTicker value={51} />
                    </div>
                    <div className='cards normal'>
                        <RevenueCard datasource={sales} />
                        <ConversionCard datasource={opportunities} />
                        <RevenueAnalysisCard datasource={salesByState} />
                        <RevenueSnapshotCard datasource={salesByCategory} />
                    </div>
                </ToolbarAnalytics>
                <LoadPanel container='.content' visible={isLoading} position={{ of: '.layout-body' }} />
            </ScrollView>
        </div>
    );
};
