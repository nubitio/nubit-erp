import "./SaleConsultPage.scss";
import DateBox from "devextreme-react/date-box";
import {Button} from "devextreme-react";
import {useRef, useState} from "react";
import Form, {FormRef, SimpleItem} from "devextreme-react/form";
import {Card} from "../../components";
import SelectBox from "devextreme-react/select-box";

const reportTypes = [
    {value: 'sale-by-product', text: 'Ventas por producto'},
    {value: 'detailed-sales', text: 'Ventas detalladas'},
    {value: 'accounting-report', text: 'Ventas contable'},
    {value: 'voided-sales', text: 'Comunicaciones de baja'},
    {value: 'credit-notes', text: 'Notas de credito valorizado'},
    {value: 'sale-notes', text: 'Reporte de notas de pedido'},
];

export const SaleConsultPage = () => {
    const [selectedReport, setSelectedReport] = useState<string>();
    const [startDate, setStartDate] = useState<Date>();
    const [endDate, setEndDate] = useState<Date>();
    const formRef = useRef<FormRef>(null);

    const handleGenerateReport = () => {
        let data = `report=${selectedReport}&date-from=${startDate?.toISOString()}&date-to=${endDate?.toISOString()}`;
        window.open(`/sale/consult?${data}`);
        formRef.current?.instance().clear();
    };
    return (
        <>
            <Card title='Reportes de venta'>
                <Form ref={formRef} width={300}>
                    <SimpleItem label={{text: 'Tipo de reporte'}}>
                        <SelectBox
                            dataSource={reportTypes}
                            displayExpr='text'
                            valueExpr='value'
                            value={selectedReport}
                            stylingMode='outlined'
                            onValueChanged={(e) => setSelectedReport(e.value)}
                        />
                    </SimpleItem>

                    <SimpleItem label={{text: 'Fecha desde'}}>
                        <DateBox
                            value={startDate}
                            stylingMode='outlined'
                            onValueChanged={(e) => setStartDate(e.value)}
                        />
                    </SimpleItem>

                    <SimpleItem label={{text: 'Fecha hasta'}}>
                        <DateBox
                            value={endDate}
                            stylingMode='outlined'
                            onValueChanged={(e) => setEndDate(e.value)}
                        />
                    </SimpleItem>

                    <SimpleItem>
                        <Button
                            text='Generar'
                            width='100%'
                            onClick={handleGenerateReport}
                            type='default'
                        />
                    </SimpleItem>
                </Form>
            </Card>
        </>
    );
};
