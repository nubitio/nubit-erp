import {consultationService} from "../services/consultation.service";
import {useService} from "../core/common/UseService";
import {patientService} from "../services/patient.service";

export const MedicalRecord = (props: any) => {
    const [consultations] = useService(consultationService.getConsultationsByPatientId(props.id), []);
    const [patient] = useService(patientService.getPatientById(props.id), {});

    return (
        <div>
            <div className="row">
                <div className="col-md-3">
                    <div className="card">
                        <div className="card-body">
                            {patient?.full_name}
                            <div className="text-muted small">{patient?.email}</div>
                            <div className="text-muted small">{patient?.phone}</div>
                        </div>
                    </div>
                </div>
                <div className="col-md-9">
                    <div className="card">
                        <div className="card-body">
                            <table className="table table-striped">
                                <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Doctor</th>
                                    <th>Diagnosis</th>
                                    <th>Prescription</th>
                                </tr>
                                </thead>
                                <tbody>
                                {consultations?.map((consultation: any) => (
                                    <tr key={consultation.id}>
                                        <td>{consultation.date}</td>
                                        <td>{consultation.physician.full_name}</td>
                                        <td>{consultation.diagnosis}</td>
                                        <td>{consultation.prescription}</td>
                                    </tr>
                                ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default MedicalRecord;