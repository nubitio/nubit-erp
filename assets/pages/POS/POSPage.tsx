import React, {useEffect, useState} from 'react';
import {ArrowLeft, CreditCard, DollarSign, Receipt, Search} from "lucide-react";
import Splitter, {Item} from 'devextreme-react/splitter';
import './POSPage.scss';
import {POSItem} from "./POSItem";
import type {Category, PosItem} from './types';
import {httpClient} from "../../core/http/HttpClient";
import {Constants} from "../../core/common/Constants";

const POSPage = () => {
    const [categories, setCategories] = useState<Category[]>([]);
    const [menuData, setMenuData] = useState<PosItem[]>([]);
    const [cart, setCart] = useState<PosItem[]>([]);
    const [activeCategory, setActiveCategory] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState("");

    const addToCart = (item: PosItem) => {
        setCart((prevCart) => {
            const existingItem = prevCart.find(cartItem => cartItem.producto_id === item.producto_id);
            if (existingItem) {
                return prevCart.map(cartItem =>
                    cartItem.producto_id === item.producto_id
                        ? {...cartItem, quantity: (cartItem.quantity || 0) + 1}
                        : cartItem
                );
            }
            return [...prevCart, {...item, quantity: 1}];
        });
    };

    const removeFromCart = (id: string) => {
        setCart((prevCart) =>
            prevCart
                .map(cartItem => cartItem.producto_id === id
                    ? {...cartItem, quantity: (cartItem.quantity || 0) - 1}
                    : cartItem)
                .filter(cartItem => (cartItem.quantity || 0) > 0)
        );
    };

    const subtotal = cart.reduce((sum, item) => sum + item.precio * (item.quantity || 0), 0);
    const tax = subtotal * 0.1;
    const total = subtotal + tax;

    const filteredMenu = menuData.filter(item =>
        (!activeCategory || item.categoria.categoria_id === activeCategory) &&
        item.descripcion.toLowerCase().includes(searchQuery.toLowerCase())
    );

    useEffect(() => {
        httpClient.get<Category[]>(`${Constants.API_BASE_URL}categories`).subscribe(response => {
            setCategories(response.data);
        });

        httpClient.get<PosItem[]>(`${Constants.API_BASE_URL}products`).subscribe(response => {
            setMenuData(response.data);
        });
    }, []);

    return (
        <Splitter id="pos-splitter">
            <Item resizable minSize="65%" size="70%">
                <Splitter orientation="vertical">
                    <Item resizable={false} collapsible={false} size="210px">
                        <div className="flex bg-gray-100">
                            <div className="flex-1 p-5 overflow-auto">
                                {/* Toolbar */}
                                <div className="flex items-center mb-4">
                                    <button
                                        onClick={() => window.history.back()}
                                        className="flex items-center text-blue-500 hover:text-blue-700 mr-4"
                                    >
                                        <ArrowLeft className="w-6 h-6 mr-2"/>
                                    </button>
                                    <div className="relative flex-1">
                                        <Search
                                            className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-500"/>
                                        <input
                                            type="text"
                                            className="pl-10 pr-4 py-2 w-full rounded-lg focus:ring-2 focus:ring-blue-500 bg-white border border-gray-200"
                                            placeholder="Buscar productos..."
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                        />
                                    </div>
                                </div>

                                {/* Category Buttons */}
                                <div className="grid grid-cols-6 gap-4">
                                    <button
                                        className={`flex items-center gap-3 rounded-xl p-4 transition-colors ${
                                            activeCategory === null ? "bg-blue-500 text-white" : "bg-white hover:bg-gray-50"
                                        }`}
                                        onClick={() => setActiveCategory(null)}
                                    >
                                        <div className="text-left font-semibold">Todos</div>
                                    </button>
                                    {categories.map(category => (
                                        <button
                                            key={category.categoria_id}
                                            className={`flex items-center gap-3 rounded-xl p-4 transition-colors ${
                                                activeCategory === category.categoria_id ? "bg-blue-500 text-white" : "bg-white hover:bg-gray-50"
                                            }`}
                                            onClick={() => setActiveCategory(category.categoria_id)}
                                        >
                                            <div className="text-left font-semibold">{category.descripcion}</div>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </Item>
                    <Item resizable={false} collapsible={false}>
                        <div className="flex bg-gray-100">
                            <div className="flex-1 p-5 overflow-auto">
                                {/* Product List */}
                                <div
                                    className="grid grid-cols-3 gap-6">
                                    {filteredMenu.map(item => (
                                        <POSItem
                                            key={item.producto_id}
                                            item={item}
                                            onAdd={addToCart}
                                            onRemove={removeFromCart}
                                            quantity={cart.find(cartItem => cartItem.producto_id === item.producto_id)?.quantity || 0}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    </Item>
                </Splitter>
            </Item>

            {/* Invoice Section */}
            <Item resizable size="30%" minSize="25%" collapsible>
                <div className="h-full flex p-5 bg-gray-100">
                    <div className="h-full flex flex-col p-4 bg-white">
                        <div className="flex-1 space-y-6">
                            {cart.map(item => (
                                <div key={item.producto_id} className="flex gap-4">
                                    <img
                                        src={item.imagen?.path || `https://ui-avatars.com/api/?name=${item.descripcion}`}
                                        alt={item.descripcion}
                                        className="w-12 h-12 rounded-lg object-cover"
                                    />
                                    <div className="flex-1">
                                        <h3 className="font-bold">{item.descripcion}</h3>
                                        <div className="text-sm text-gray-500">
                                            {item.quantity}× <span className="ml-2">{item.precio.toFixed(2)}</span>
                                        </div>
                                    </div>
                                    <div className="font-bold">${(item.precio * (item.quantity || 0)).toFixed(2)}</div>
                                </div>
                            ))}
                        </div>
                        <div className="border-t mt-6 pt-6 space-y-4">
                            <div className="flex justify-between">
                                <span className="text-gray-600">Sub Total</span>
                                <span className="font-bold">${subtotal.toFixed(2)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-600">Tax</span>
                                <span className="font-bold">${tax.toFixed(2)}</span>
                            </div>
                            <div className="flex justify-between text-lg font-bold">
                                <span>Total Payment</span>
                                <span>${total.toFixed(2)}</span>
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-4 mt-6">
                            <button
                                className="p-4 flex flex-col items-center gap-2 rounded-xl bg-blue-50 text-blue-500">
                                <CreditCard className="w-6 h-6"/>
                                <span className="text-sm">Credit Card</span>
                            </button>
                            <button className="p-4 flex flex-col items-center gap-2 rounded-xl bg-gray-50">
                                <Receipt className="w-6 h-6"/>
                                <span className="text-sm">Paylater</span>
                            </button>
                            <button className="p-4 flex flex-col items-center gap-2 rounded-xl bg-gray-50">
                                <DollarSign className="w-6 h-6"/>
                                <span className="text-sm">Cash Payout</span>
                            </button>
                        </div>
                        <button className="w-full mt-6 py-4 bg-blue-500 text-white rounded-xl font-semibold">
                            Place Order
                        </button>
                    </div>
                </div>
            </Item>
        </Splitter>
    );
};

export default POSPage;
