export interface PosItem {
    producto_id: string
    descripcion: string
    precio: number
    imagen?: Image
    quantity?: number
    categoria: Category
}

export interface Category {
    categoria_id: string
    descripcion: string
    icon?: string
    active?: boolean
}

export interface Image {
    id: string
    path: string
}
