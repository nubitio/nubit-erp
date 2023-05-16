import { MediaDto } from "./media.dto";

export interface ProductDto {
    producto_id: string;
    imagen: MediaDto;
    codigo: string;
    descripcion: string;
    precio: number;
    categoria_id: string;
    categoria: string;
    estado: string;
}