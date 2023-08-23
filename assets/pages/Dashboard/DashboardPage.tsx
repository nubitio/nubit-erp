import "./DashboardPage.scss";
import ScrollView from "devextreme-react/scroll-view";
import {
    ConversionCard,
    RevenueAnalysisCard,
    RevenueCard,
    RevenueSnapshotCard,
    ToolbarAnalytics
} from "../../components";
import LoadPanel from "devextreme-react/load-panel";
import {useEffect, useState} from "react";
import {ANALYTICS_PERIODS, DEFAULT_ANALYTICS_PERIOD_KEY} from "../../shared/constants";
import {Sale, SaleByState, SaleOrOpportunityByCategory} from "../../types/analytics";

export const DashboardPage = () => {
    const container = "wrapper"
    const [dateRange, setDateRange] = useState(ANALYTICS_PERIODS[DEFAULT_ANALYTICS_PERIOD_KEY].period.split('/'));
    const [opportunities, setOpportunities] = useState<SaleOrOpportunityByCategory[]>([]);
    const [salesByCategory, setSalesByCategory] = useState<SaleOrOpportunityByCategory[]>([]);
    const [sales, setSales] = useState<Sale[]>([]);
    const [salesByState, setSalesByState] = useState<SaleByState[]>([]);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        Promise.all([
            fetch('/bi/venta-semana')
                .then((response) => response.json())
                .then((data) => {
                    setSales(data);
                }),
        ])
            .then(() => setIsLoading(false))
            .catch((error) => console.log(error));
    }, [dateRange]);

    return (
        <div id={container}>
            <ScrollView className='view-wrapper-scroll'>
                <ToolbarAnalytics
                    title='Dashboard'
                >
                    <div className='cards compact'>
                    </div>
                    <div className='cards normal'>
                        <RevenueCard datasource={sales}/>
                        <ConversionCard datasource={opportunities}/>
                        <RevenueAnalysisCard datasource={salesByState}/>
                        <RevenueSnapshotCard datasource={salesByCategory}/>
                    </div>
                </ToolbarAnalytics>
                <LoadPanel container='.content' visible={isLoading} position={{of: '.layout-body'}}/>
            </ScrollView>
        </div>
    );
};
