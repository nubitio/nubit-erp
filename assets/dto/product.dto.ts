import { MediaDto } from "./media.dto";
import {UnitOfMeasureDto} from "./UnitOfMeasureDto";

export interface ProductDto {
    producto_id: string;
    tipo_id: number;
    imagen: MediaDto;
    codigo: string;
    descripcion: string;
    precio: number;
    categoria_id: string;
    categoria: string;
    unidad: UnitOfMeasureDto;
    estado: string;
}
