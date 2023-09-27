import {consultationService} from "../../../../services/consultation.service";
import {useService} from "../../../../core/common/UseService";
import {patientService} from "../../../../services/patient.service";
import {ConsultationDto} from "../../../../dto/consultation.dto";
import {DateUtils} from "../../../../core/utils/DateUtils";
import {PatientDto} from "../../../../dto/patient.dto";
import "./MedicalRecord.scss";

export const MedicalRecord = (props: any) => {
    const [consultations] = useService<ConsultationDto[]>(consultationService.getConsultationsByPatientId(props.id), []);
    const [patient] = useService<PatientDto>(patientService.getPatientById(props.id), {});

    return (
        <div className="container">
            <div className="row mt-4">
                <div className="col-md-4">
                    <div className="card">
                        <div className="card-body">
                            <h5 className="card-title">Datos del paciente</h5>
                            <p className="card-text"><strong>Nombre:</strong> {patient.full_name}</p>
                        </div>
                    </div>
                </div>

                <div className="col-md-8">
                    <div className="card">
                        <div className="card-body">
                            <h5 className="card-title">Historial de consultas</h5>
                            {consultations.map((consultation) => (
                                <div className="card consultation-card mt-4" key={consultation.id}>
                                    <div className="card-header">
                                        <h5 className="card-title">{DateUtils.format(consultation.date)}</h5>
                                    </div>
                                    <div className="card-body">
                                        <p className="card-text"><strong>Motivo de
                                            consulta:</strong> {consultation.reason}</p>
                                        <p className="card-text">
                                            <strong>Médico:</strong> {consultation.physician.full_name}</p>
                                        <h5 className="card-title">Diagnóstico:</h5>
                                        <p className="card-text">{consultation.diagnosis}</p>
                                        <h5 className="card-title">Tratamiento:</h5>
                                        <p className="card-text">{consultation.treatment}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default MedicalRecord;
