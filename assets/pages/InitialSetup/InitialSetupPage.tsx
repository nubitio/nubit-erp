import React, {useEffect, useState} from "react";
import {useNavigate} from "react-router-dom";
import LoadIndicator from "devextreme-react/load-indicator";
import notify from "devextreme/ui/notify";

import "./InitialSetupPage.scss";
import {setupService, SetupPayload, SetupStatus} from "../../service/setup.service";
import {useScreenSizeClass} from "../../utils/media-query";

const steps = [
    {id: 'admin', title: 'Cuenta administrador', description: 'Define las credenciales del usuario raíz.'},
    {id: 'company', title: 'Empresa', description: 'Datos fiscales y de contacto de la compañía.'},
    {id: 'branch', title: 'Sucursal principal', description: 'Primer local desde el que operarás.'},
] as const;

const defaultFormData: SetupPayload = {
    adminUsername: '',
    adminPassword: '',
    companyRuc: '',
    companyBusinessName: '',
    companyTradeName: '',
    companyFiscalAddress: '',
    companyPhone: '',
    branchName: '',
    branchAddress: '',
    branchPhone: '',
};

export const InitialSetupPage = () => {
    const navigate = useNavigate();
    const screenSizeClass = useScreenSizeClass();

    const [formData, setFormData] = useState<SetupPayload>(defaultFormData);
    const [status, setStatus] = useState<SetupStatus | null>(null);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [stepIndex, setStepIndex] = useState(0);

    useEffect(() => {
        const subscription = setupService.getStatus().subscribe({
            next: (response) => {
                const data = response.data;
                setStatus(data);
                if (data.configured || data.userCount > 0) {
                    navigate('/');
                    return;
                }
                setLoading(false);
            },
            error: () => setLoading(false),
        });

        return () => subscription.unsubscribe();
    }, [navigate]);

    const updateField = (field: keyof SetupPayload) => (event: React.ChangeEvent<HTMLInputElement>) => {
        const value = event.target.value;
        setFormData((prev) => ({...prev, [field]: value}));
    };

    const validateStep = (currentStep: typeof steps[number]['id']): boolean => {
        const errors: string[] = [];

        if (currentStep === 'admin') {
            if (!formData.adminUsername.trim()) {
                errors.push('Usuario administrador es obligatorio.');
            }
            if (formData.adminPassword.trim().length < 6) {
                errors.push('La contraseña debe tener al menos 6 caracteres.');
            }
        }

        if (currentStep === 'company') {
            if (!/^\d{11}$/.test(formData.companyRuc.trim())) {
                errors.push('El RUC debe tener 11 dígitos.');
            }
            if (!formData.companyBusinessName.trim()) {
                errors.push('La razón social es obligatoria.');
            }
            if (!formData.companyFiscalAddress.trim()) {
                errors.push('El domicilio fiscal es obligatorio.');
            }
        }

        if (currentStep === 'branch') {
            if (!formData.branchName.trim()) {
                errors.push('El nombre de la sucursal es obligatorio.');
            }
        }

        if (errors.length) {
            notify(errors.join('\n'), 'error', 3500);
            return false;
        }

        return true;
    };

    const goNext = () => {
        const currentStep = steps[stepIndex].id;
        if (!validateStep(currentStep)) {
            return;
        }
        if (stepIndex < steps.length - 1) {
            setStepIndex((prev) => prev + 1);
        } else {
            submit();
        }
    };

    const goBack = () => {
        if (stepIndex > 0) {
            setStepIndex((prev) => prev - 1);
        }
    };

    const submit = () => {
        setSubmitting(true);
        setupService.complete(formData).subscribe({
            next: (response) => {
                setStatus(response.data);
                notify('Configuración inicial completada', 'success', 3000);
                setSubmitting(false);
                navigate('/login');
            },
            error: (err) => {
                const message = err?.response?.data?.detail || 'No se pudo completar la configuración.';
                notify(message, 'error', 4000);
                setSubmitting(false);
            },
        });
    };

    if (loading) {
        return (
            <div className={`initial-setup-page ${screenSizeClass}`}>
                <div className="loading">
                    <LoadIndicator visible width={48} height={48}/>
                    <p>Preparando asistente...</p>
                </div>
            </div>
        );
    }

    const currentStep = steps[stepIndex].id;

    return (
        <div className={`initial-setup-page ${screenSizeClass}`}>
            <div className="setup-shell">
                <header className="setup-header">
                    <div>
                        <p className="eyebrow">Asistente de primer uso</p>
                        <h1>{steps[stepIndex].title}</h1>
                        <p className="subtitle">{steps[stepIndex].description}</p>
                    </div>
                </header>

                <div className="stepper">
                    {steps.map((step, index) => {
                        const isActive = index === stepIndex;
                        const isDone = index < stepIndex;
                        return (
                            <div key={step.id} className={`step ${isActive ? 'active' : ''} ${isDone ? 'done' : ''}`}>
                                <div className="step-marker">
                                    {isDone ? 'OK' : index + 1}
                                </div>
                                <div className="step-text">
                                    <div className="step-title">{step.title}</div>
                                    <div className="step-desc">{step.description}</div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                <div className="setup-card">
                    {currentStep === 'admin' && (
                        <div className="fields-grid two-columns">
                            <Field
                                label="Usuario administrador"
                                placeholder="Ej. admin"
                                value={formData.adminUsername}
                                onChange={updateField('adminUsername')}
                                autoFocus
                            />
                            <Field
                                label="Contraseña"
                                placeholder="Mínimo 6 caracteres"
                                type="password"
                                value={formData.adminPassword}
                                onChange={updateField('adminPassword')}
                            />
                        </div>
                    )}

                    {currentStep === 'company' && (
                        <div className="fields-grid two-columns">
                            <Field
                                label="RUC"
                                placeholder="11 dígitos"
                                value={formData.companyRuc}
                                onChange={updateField('companyRuc')}
                            />
                            <Field
                                label="Razón social"
                                placeholder="Razón social"
                                value={formData.companyBusinessName}
                                onChange={updateField('companyBusinessName')}
                            />
                            <Field
                                label="Nombre comercial"
                                placeholder="Opcional"
                                value={formData.companyTradeName || ''}
                                onChange={updateField('companyTradeName')}
                            />
                            <Field
                                label="Domicilio fiscal"
                                placeholder="Dirección fiscal"
                                value={formData.companyFiscalAddress}
                                onChange={updateField('companyFiscalAddress')}
                            />
                            <Field
                                label="Teléfono"
                                placeholder="Opcional"
                                value={formData.companyPhone || ''}
                                onChange={updateField('companyPhone')}
                            />
                        </div>
                    )}

                    {currentStep === 'branch' && (
                        <div className="fields-grid two-columns">
                            <Field
                                label="Nombre de la sucursal"
                                placeholder="Ej. Local Central"
                                value={formData.branchName}
                                onChange={updateField('branchName')}
                            />
                            <Field
                                label="Dirección"
                                placeholder="Opcional"
                                value={formData.branchAddress || ''}
                                onChange={updateField('branchAddress')}
                            />
                            <Field
                                label="Teléfono"
                                placeholder="Opcional"
                                value={formData.branchPhone || ''}
                                onChange={updateField('branchPhone')}
                            />
                        </div>
                    )}

                    <div className="actions">
                        <button
                            type="button"
                            className="ghost"
                            onClick={goBack}
                            disabled={stepIndex === 0 || submitting}
                        >
                            Atrás
                        </button>
                        <button
                            type="button"
                            className="primary"
                            onClick={goNext}
                            disabled={submitting}
                        >
                            {submitting ? <LoadIndicator visible width={20} height={20}/> : stepIndex === steps.length - 1 ? 'Finalizar configuración' : 'Siguiente'}
                        </button>
                    </div>
                    <div className="helper-links">
                        <button type="button" className="linkish" onClick={() => navigate('/login')} disabled={submitting}>
                            ¿Ya configuraste? Ir a inicio de sesión
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

type FieldProps = {
    label: string;
    placeholder?: string;
    type?: string;
    value: string;
    autoFocus?: boolean;
    onChange: (event: React.ChangeEvent<HTMLInputElement>) => void;
};

const Field = ({label, placeholder, type = 'text', value, onChange, autoFocus}: FieldProps) => (
    <label className="field">
        <span>{label}</span>
        <input
            type={type}
            placeholder={placeholder}
            value={value}
            onChange={onChange}
            autoFocus={autoFocus}
        />
    </label>
);
