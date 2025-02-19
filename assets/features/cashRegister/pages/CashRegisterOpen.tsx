import {Card} from "../../../components";
import Form, {FormRef, SimpleItem} from "devextreme-react/form";
import {useRef, useState} from "react";
import {Button} from "devextreme-react";
import LoadIndicator from "devextreme-react/load-indicator";
import {FieldBuilder} from "../../../core/field/FieldBuilder";
import {FieldType} from "../../../core/field/FieldType";
import {buildField} from "../../../core/form/FormUtils";
import cashRegisterService from "../services/cashRegisterService";
import {useNavigate, useParams} from "react-router-dom";
import toast from "../../../core/utils/SharedUtils";

type CashRegisterOpenParams = {
    id: string;
}

export const CashRegisterOpen = () => {
    const formRef = useRef<FormRef>(null);
    const [isProcessing, setIsProcessing] = useState(false);
    const {id} = useParams<CashRegisterOpenParams>();
    const navigate = useNavigate();

    const openingBalanceField = new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('openingBalance')
        .label('Saldo inicial')
        .required(true)
        .build();

    const openCashRegister = () => {
        const formData = formRef.current?.instance().option('formData');
        if (id === undefined || formData === undefined) {
            toast('No se pudo abrir la caja', 'error');
            return;
        }
        setIsProcessing(true);
        cashRegisterService().openCashRegister(id, formData.openingBalance).subscribe((response) => {
            toast('Caja abierta', 'success');
            const cashShiftId = response.data.id;
            navigate('/cash-shift/' + cashShiftId + '/movements');
        }).add(() => {
            setIsProcessing(false);
        })
    }

    return (
        <>
            <Card title='Apertura de caja'>
                <Form ref={formRef} width={300}>
                    {buildField(openingBalanceField, openingBalanceField.name)}

                    <SimpleItem>
                        <Button
                            width='100%'
                            onClick={openCashRegister}
                            type='success'
                        >
                            <LoadIndicator className="button-indicator" visible={isProcessing}/>
                            <span className="dx-button-text">{isProcessing ? 'Procesando...' : 'Abrir caja'}</span>
                        </Button>
                    </SimpleItem>
                </Form>
            </Card>
        </>
    );
}
