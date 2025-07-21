type ValidationOptions = {
    message: string;
    min?: number;
    max?: number;
    comparisonTarget?: () => any;
    comparisonType?: '==' | '!=' | '>' | '<' | '>=' | '<=';
    pattern?: string;
    validationCallback?: (options: any) => boolean;
};

export type ValidationRule = {
    type:
        | 'async'
        | 'compare'
        | 'custom'
        | 'email'
        | 'numeric'
        | 'pattern'
        | 'range'
        | 'required'
        | 'stringLength';
    options: ValidationOptions;
};
