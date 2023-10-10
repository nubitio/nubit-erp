import {useEffect, useRef, useState} from "react";
import {InputDate, InputDateEvents} from "./Components/InputDateReporting";

export const ReportingPage = () => {
    const reportingRef = useRef<InputDateEvents>(null);
    const initialRef = useRef<InputDateEvents>(null);
    const [securityLevel, setSecurityLevel] = useState(0)

    const setDefaultValue = () => {
        reportingRef.current?.setValue("Demo");
    }

    const incrementByOne = () => {
        setSecurityLevel(securityLevel + 1)
    }


    useEffect(() => {
        reportingRef.current?.setValue("Admin");
    }, [securityLevel]);

    useEffect( () => {
        initialRef.current?.setValue('2024-01-01')
    });

    return (
        <div>
            <h1>BIReportDashboard</h1>
            <form>
                <button type="button" onClick={setDefaultValue}>
                    SetValue
                </button>
                <button type="button" onClick={incrementByOne}>
                    Add+1
                </button>
                <button type="button" onClick={() => {
                    reportingRef.current?.clear();
                    initialRef.current?.clear();
                }}>
                    clean
                </button>
                <InputDate
                    ref={initialRef}
                    placeholder={"Type init date"}
                    name={"init"}
                    label={"Initial date"}
                    required={true}
                    default={"01/09/2023"}></InputDate>
                <InputDate
                    placeholder={"Type final date"}
                    name={"final"}
                    label={"Final date"}
                    required={true}
                    default={"21/08/2023"}></InputDate>
                <InputDate
                    ref={reportingRef}
                    placeholder={"local"}
                    name={"local"}
                    label={"Local"}
                    required={true}
                    default={"AQP"}></InputDate>
                <h2>{securityLevel}</h2>
            </form>
        </div>
    )
}
