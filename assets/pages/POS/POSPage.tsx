import React, {useEffect, useState} from 'react';
import {CreditCard, DollarSign, Receipt} from "lucide-react"
import Splitter, {Item} from 'devextreme-react/splitter';
import './POSPage.scss';
import {POSItem} from "./POSItem";
import type {Category, PosItem} from './types';
import {httpClient} from "../../core/http/HttpClient";
import {Constants} from "../../core/common/Constants";

const POSPage = () => {
    const [categories, setCategories] = useState<Category[]>([]);
    const [menuData, setMenuData] = useState<PosItem[]>([]);
    const [cart, setCart] = useState([] as any[]);
    const [activeCategory, setActiveCategory] = useState('All');

    const addToCart = (item: PosItem) => {
        setCart((prevCart) => {
            const existingItem = prevCart.find((cartItem) => cartItem.id === item.producto_id);
            if (existingItem) {
                return prevCart.map((cartItem) =>
                    cartItem.id === item.producto_id ? {...cartItem, quantity: cartItem.quantity + 1} : cartItem
                );
            }
            return [...prevCart, {...item, quantity: 1}];
        });
    };

    const removeFromCart = (id) => {
        setCart((prevCart) => prevCart.filter((item) => item.id !== id));
    }

    const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const tax = subtotal * 0.1;
    const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);

    const filteredMenu = activeCategory === 'All'
        ? menuData
        : menuData.filter((item) => item.category.categoria_id === activeCategory);

    useEffect(() => {
        httpClient.get<Category[]>(Constants.API_BASE_URL + 'categories').subscribe((response) => {
            console.log(response.data);
            setCategories(response.data);
        });

        httpClient.get<PosItem[]>(Constants.API_BASE_URL + 'products').subscribe((response) => {
            console.log(response.data);
            setMenuData(response.data);
        });
    }, []);

    return (
        <Splitter id="pos-splitter">
            {/* Menu Section */}
            <Item resizable={true} minSize="65%" size="70%">
                <div className="flex h-screen bg-gray-100">
                    <div className="flex-1 p-6 overflow-auto">
                        <div className="menu-section">
                            {/* Categories */}
                            <div className="grid grid-cols-6 gap-4 mb-8">
                                {categories.map((category: Category) => (
                                    <button
                                        key={category.descripcion}
                                        className={`flex items-center gap-3 rounded-xl p-4 transition-colors ${
                                            category.active ? "bg-blue-500 text-white" : "bg-white hover:bg-gray-50"
                                        }`}
                                    >
                                        <div className="text-left">
                                            <div className="font-semibold">{category.descripcion}</div>
                                        </div>
                                    </button>
                                ))}
                            </div>

                            {/* Menu Grid */}
                            <div className="grid grid-cols-4 gap-6">
                                {filteredMenu.map((item) => (
                                    <POSItem
                                        key={item.producto_id}
                                        item={item}
                                        onAdd={addToCart}
                                        onRemove={removeFromCart}
                                        quantity={(cart.find((cartItem) => cartItem.id === item.producto_id) || {quantity: 0}).quantity}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </Item>

            {/* Invoice Section */}
            <Item resizable={true} size="30%" minSize="25%" collapsible={true}>
                <div className="h-full flex flex-col border-l bg-white p-6">
                    <h2 className="text-2xl font-bold mb-6">Invoice</h2>
                    <div className="flex-1 space-y-6">
                        {cart.map((item) => (
                            <div key={item.producto_id} className="flex gap-4">
                                <img
                                    src={item.imagen?.path || "https://fakeimg.pl/600x400?text=" + item.descripcion}
                                    alt={item.descripcion}
                                    className="w-16 h-16 rounded-lg object-cover"
                                />
                                <div className="flex-1">
                                    <h3 className="font-bold">{item.descripcion}</h3>
                                    <div className="text-sm text-gray-500">
                                        {item.quantity}× <span className="ml-2">{item.note}</span>
                                    </div>
                                </div>
                                <div className="font-bold">${item.precio}</div>
                            </div>
                        ))}
                    </div>
                    <div className="border-t mt-6 pt-6 space-y-4">
                        <div className="flex justify-between">
                            <span className="text-gray-600">Sub Total</span>
                            <span className="font-bold">${subtotal.toFixed(1)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Tax</span>
                            <span className="font-bold">${tax.toFixed(1)}</span>
                        </div>
                        <div className="flex justify-between text-lg font-bold">
                            <span>Total Payment</span>
                            <span>${total.toFixed(1)}</span>
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
            </Item>
        </Splitter>
    );
};

export default POSPage;
