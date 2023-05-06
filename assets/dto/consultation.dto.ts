import {PatientDto} from "./patient.dto";
import {PhysicianDto} from "./physician.dto";

export interface ConsultationDto {
    id: number
    patient: PatientDto
    physician: PhysicianDto
    date: string
    reason: string
    diagnosis: string
    treatment: string
    notes: string
    created_at: string
    updated_at: string
}