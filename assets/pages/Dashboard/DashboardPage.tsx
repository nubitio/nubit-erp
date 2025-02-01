import "./DashboardPage.scss";
import {useEffect, useState} from "react";
import {httpClient} from "../../core/http/HttpClient";
import {Constants} from "../../core/common/Constants";
import {Chart} from "devextreme-react";
import {Format, Legend, Series, Size, Title, Tooltip, ValueAxis} from "devextreme-react/chart";
import {Label} from "devextreme-react/form";

export const DashboardPage = () => {
    const container = "wrapper"
    const [weeklySales, setWeeklySales] = useState<any[]>([]);
    const [monthlySales, setMonthlySales] = useState<any[]>([]);
    const [dailySales, setDailySales] = useState<any[]>([]);
    const [monthlyInvoicing, setMonthlyInvoicing] = useState<any[]>([]);

    useEffect(() => {
        httpClient.get<any>(Constants.API_BASE_URL + "bi/venta-dia").subscribe((response) => {
            const data = response.data;
            console.log(data);
            setDailySales(data);
        });
        httpClient.get<any>(Constants.API_BASE_URL + "bi/venta-semana").subscribe((response) => {
            const data = response.data;
            console.log(data);
            setWeeklySales(data);
        });
        httpClient.get<any>(Constants.API_BASE_URL + "bi/venta-mes").subscribe((response) => {
            const data = response.data;
            console.log(data);
            setMonthlySales(data);
        });
        httpClient.get<any>(Constants.API_BASE_URL + "bi/monthly-invoicing").subscribe((response) => {
            const data = response.data;
            console.log(data);
            setMonthlyInvoicing(data);
        });
    }, []);

    return (
        <div id={container}>
            <div className="w-full h-screen overflow-auto p-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="border rounded-lg shadow-lg p-4 min-w-[320px]" style={{height: "350px"}}>
                        <Chart id="weekly-sales" dataSource={weeklySales}>
                            <Size height={320}/>
                            <Title text="Ventas de la semana"/>
                            <Series
                                valueField="venta"
                                argumentField="dia"
                                name="Ventas"
                                type="bar"
                            />
                            <ValueAxis>
                                <Label>
                                    <Format type='currency' currency='PEN'/>
                                </Label>
                            </ValueAxis>
                            <Tooltip enabled={true} format="currency"/>
                            <Legend visible={false}/>
                        </Chart>
                    </div>
                    <div className="border rounded-lg shadow-lg p-4 min-w-[320px]" style={{height: "350px"}}>
                        <Chart id="daily-sales" dataSource={dailySales}>
                            <Size height={320}/>
                            <Title text="Ventas del mes"/>
                            <Series
                                valueField="venta"
                                argumentField="dia"
                                name="Ventas"
                                type="spline"
                            />
                            <ValueAxis>
                                <Label>
                                    <Format type='currency' currency='PEN'/>
                                </Label>
                            </ValueAxis>
                            <Tooltip enabled={true} format="currency"/>
                            <Legend visible={false}/>
                        </Chart>
                    </div>
                    <div className="border rounded-lg shadow-lg p-4 min-w-[320px]" style={{height: "350px"}}>
                        <Chart id="monthly-sales" dataSource={monthlySales}>
                            <Size height={320}/>
                            <Title text="Ventas de los últimos 12 meses"/>
                            <Series
                                valueField="venta"
                                argumentField="mes"
                                name="Ventas"
                                type="bar"
                            />
                            <ValueAxis>
                                <Label>
                                    <Format type='currency' currency='PEN'/>
                                </Label>
                            </ValueAxis>
                            <Tooltip enabled={true} format="currency"/>
                            <Legend visible={false}/>
                        </Chart>
                    </div>
                    <div className="border rounded-lg shadow-lg p-4 min-w-[320px]" style={{height: "350px"}}>
                        <Chart id="monthly-invoicing" dataSource={monthlyInvoicing}>
                            <Size height={320}/>
                            <Title text="Facturación de los últimos 12 meses"/>
                            <Series
                                valueField="total"
                                argumentField="month"
                                name="Ventas"
                                type="bar"
                            />
                            <ValueAxis>
                                <Label>
                                    <Format type='fixedPoint' precision={0}/>
                                </Label>
                            </ValueAxis>
                            <Tooltip enabled={true}/>
                            <Legend visible={false}/>
                        </Chart>
                    </div>
                </div>
            </div>
        </div>
    );
};
