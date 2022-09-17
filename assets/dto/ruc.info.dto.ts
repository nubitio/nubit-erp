export interface RucInfoDto {
    ruc: string;
    razonSocial: string;
    nombreComercial: string;
    tipo: string;
    estado: string;
    condicion: string;
    direccion: string;
    departamento: string;
    provincia: string;
    distrito: string;
    fechaInscripcion: Date;
    sistEmsion: string;
    sistContabilidad: string;
    actExterior: string;
    actEconomicas: string[];
    cpPago: string[];
    sistElectronica: string[];
    fechaEmisorFe: Date;
    cpeElectronico: string[];
    fechaPle: Date;
    padrones: string[];
    fechaBaja?: any;
    profesion: string;
}