import "./InputsCss.scss"
import {forwardRef, useImperativeHandle, useRef} from "react";
export const InputDate = forwardRef<InputDateEvents, InputDateProps>((props, ref) => {
    const inputRef = useRef<HTMLInputElement>(null);
    const getValue = () => {
        return inputRef.current?.value || 'nothing null';
    }

    const setValue = (value: string) => {
        inputRef.current!.value = value;
    }

    const clear = () => {
        inputRef.current!.value = '';
    }

    useImperativeHandle(ref, () => ({
        getValue,
        setValue,
        clear
    }));

    return (
        <div className={"DateReport"}>
            <label>
                <h4>{props.label}</h4>
            </label>
            <input
                ref={inputRef}
                type="text"
                className={"textbox"}
                name={props.name}
                required={props.required}
                value={props.default}
            />
        </div>
    )
})

export interface InputDateProps {
    placeholder: string;
    name: string;
    label: string;
    required: boolean;
    default?: string;
}

export interface InputDateEvents {
    getValue: () => string;
    setValue: (value: string) => void;
    clear: () => void;
}



